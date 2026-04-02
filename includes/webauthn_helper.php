<?php
/**
 * WebAuthn Helper Functions
 * Wraps lbuchs/WebAuthn library for biometric authentication
 */

if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/../vendor/autoload.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;

/**
 * Decode a base64url string to binary
 */
function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Create a WebAuthn instance with app configuration
 */
function webauthn_create_instance() {
    return new WebAuthn(WEBAUTHN_RP_NAME, WEBAUTHN_RP_ID, ['none']);
}

/**
 * Generate registration options (challenge) for a user
 *
 * @param int $userId
 * @param string $username
 * @param string $displayName
 * @return array Registration options for navigator.credentials.create()
 */
function webauthn_get_register_options($userId, $username, $displayName) {
    global $pdo;

    $webauthn = webauthn_create_instance();

    // Get existing credentials to exclude (prevent re-registering same authenticator)
    $excludeIds = [];
    $stmt = db_query("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?", [$userId]);
    if ($stmt) {
        while ($row = $stmt->fetch()) {
            $excludeIds[] = $row['credential_id'];
        }
    }

    // User ID as binary (hex-encode the integer)
    $userIdBinary = hex2bin(str_pad(dechex($userId), 8, '0', STR_PAD_LEFT));

    $createArgs = $webauthn->getCreateArgs(
        $userIdBinary,
        $username,
        $displayName,
        60,                // timeout in seconds
        false,             // requireResidentKey
        'required',        // userVerification — forces fingerprint/Face ID over PIN
        false,             // crossPlatformAttachment: false = platform only (biometrics)
        $excludeIds        // excludeCredentialIds
    );

    // Store challenge in session for verification
    $_SESSION['webauthn_challenge'] = $webauthn->getChallenge()->getBinaryString();
    $_SESSION['webauthn_challenge_time'] = time();

    return $createArgs;
}

/**
 * Verify registration response and return credential data
 *
 * @param string $clientDataJSON Base64-encoded from browser
 * @param string $attestationObject Base64-encoded from browser
 * @return array Credential data to store in database
 * @throws Exception on verification failure
 */
function webauthn_verify_registration($clientDataJSON, $attestationObject) {
    if (empty($_SESSION['webauthn_challenge'])) {
        throw new \Exception('No registration challenge found in session');
    }

    // Check challenge age (2 minute max)
    if (time() - ($_SESSION['webauthn_challenge_time'] ?? 0) > 120) {
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_time']);
        throw new \Exception('Registration challenge has expired');
    }

    $challenge = $_SESSION['webauthn_challenge'];
    // Clear challenge immediately (one-time use)
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_time']);

    $webauthn = webauthn_create_instance();

    $credential = $webauthn->processCreate(
        base64url_decode($clientDataJSON),
        base64url_decode($attestationObject),
        $challenge,
        true,   // requireUserVerification — must match 'required' in options
        true    // requireUserPresent
    );

    return [
        'credential_id' => $credential->credentialId,
        'public_key' => $credential->credentialPublicKey,
        'sign_count' => $credential->signatureCounter ?? 0,
    ];
}

/**
 * Generate authentication options (challenge) for a user
 *
 * @param string $username
 * @return array|null Authentication options, or null if user has no credentials
 */
function webauthn_get_login_options($username) {
    global $pdo;

    // Look up user
    $stmt = db_query("SELECT id FROM users WHERE username = ? AND status = 'active' LIMIT 1", [$username]);
    if (!$stmt || $stmt->rowCount() === 0) {
        return null;
    }
    $user = $stmt->fetch();

    // Get user's registered credentials
    $credStmt = db_query("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?", [$user['id']]);
    if (!$credStmt || $credStmt->rowCount() === 0) {
        return null;
    }

    $credentialIds = [];
    while ($row = $credStmt->fetch()) {
        $credentialIds[] = $row['credential_id'];
    }

    $webauthn = webauthn_create_instance();

    $getArgs = $webauthn->getGetArgs(
        $credentialIds,
        60,         // timeout
        true,       // allowUsb
        true,       // allowNfc
        true,       // allowBle
        true,       // allowHybrid
        true,       // allowInternal
        'required'  // userVerification — forces fingerprint/Face ID over PIN
    );

    // Store challenge in session
    $_SESSION['webauthn_challenge'] = $webauthn->getChallenge()->getBinaryString();
    $_SESSION['webauthn_challenge_time'] = time();
    $_SESSION['webauthn_user_id'] = $user['id'];

    return $getArgs;
}

/**
 * Verify authentication response
 *
 * @param string $clientDataJSON Base64-encoded from browser
 * @param string $authenticatorData Base64-encoded from browser
 * @param string $signature Base64-encoded from browser
 * @param string $credentialIdBase64 Base64-encoded credential ID from browser
 * @return array ['success' => bool, 'user_id' => int]
 * @throws Exception on verification failure
 */
function webauthn_verify_login($clientDataJSON, $authenticatorData, $signature, $credentialIdBase64) {
    if (empty($_SESSION['webauthn_challenge'])) {
        throw new \Exception('No authentication challenge found in session');
    }

    // Check challenge age
    if (time() - ($_SESSION['webauthn_challenge_time'] ?? 0) > 120) {
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_time'], $_SESSION['webauthn_user_id']);
        throw new \Exception('Authentication challenge has expired');
    }

    $challenge = $_SESSION['webauthn_challenge'];
    $userId = $_SESSION['webauthn_user_id'];
    // Clear challenge immediately
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_time'], $_SESSION['webauthn_user_id']);

    // Decode credential ID from base64url
    $credentialIdBinary = base64url_decode($credentialIdBase64);

    // Look up stored credential
    $stmt = db_query(
        "SELECT id, user_id, public_key, sign_count FROM webauthn_credentials WHERE credential_id = ? AND user_id = ?",
        [$credentialIdBinary, $userId]
    );

    if (!$stmt || $stmt->rowCount() === 0) {
        throw new \Exception('Credential not found');
    }

    $stored = $stmt->fetch();

    $webauthn = webauthn_create_instance();

    // Per WebAuthn spec: skip counter check if stored count is 0
    // (iOS/iCloud Keychain passkeys return 0 on first use — not a replay attack)
    $prevSignCount = ($stored['sign_count'] > 0) ? (int)$stored['sign_count'] : null;

    $valid = $webauthn->processGet(
        base64url_decode($clientDataJSON),
        base64url_decode($authenticatorData),
        base64url_decode($signature),
        $stored['public_key'],
        $challenge,
        $prevSignCount,
        true,   // requireUserVerification — must match 'required' in options
        true    // requireUserPresent
    );

    if ($valid) {
        // Update sign count and last used timestamp
        $newSignCount = $webauthn->getSignatureCounter();
        db_query(
            "UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?",
            [$newSignCount, $stored['id']]
        );

        return ['success' => true, 'user_id' => $stored['user_id']];
    }

    throw new \Exception('Authentication failed');
}
