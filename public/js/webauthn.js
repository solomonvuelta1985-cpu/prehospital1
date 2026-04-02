/**
 * WebAuthn (Biometric/Face ID) Client-Side Module
 * Handles registration and authentication via the Web Authentication API
 */

// ============================================================
// Utility helpers
// ============================================================

/**
 * Convert an ArrayBuffer to a base64url-encoded string
 */
function bufferToBase64url(buffer) {
    var bytes = new Uint8Array(buffer);
    var binary = '';
    for (var i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

/**
 * Convert a base64url-encoded string to an ArrayBuffer
 */
function base64urlToBuffer(base64url) {
    // Handle both base64url and standard base64
    var base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    // Add padding if needed
    while (base64.length % 4 !== 0) {
        base64 += '=';
    }
    var binary = atob(base64);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

/**
 * Decode the challenge and credential IDs in the server response.
 * The lbuchs/WebAuthn library uses RFC 1342-like encoding: "=?BINARY?B?<base64>?="
 */
function decodeBinaryValue(value) {
    if (typeof value === 'string') {
        // RFC 1342-like: =?BINARY?B?<base64>?=
        var match = value.match(/^=\?BINARY\?B\?(.+)\?=$/);
        if (match) {
            return base64urlToBuffer(match[1]);
        }
        // Plain base64url
        return base64urlToBuffer(value);
    }
    if (value && typeof value === 'object') {
        // Already an object with base64url encoding
        if (value.b64) return base64urlToBuffer(value.b64);
        if (value.base64url) return base64urlToBuffer(value.base64url);
    }
    return value;
}

// ============================================================
// Feature detection
// ============================================================

/**
 * Check if WebAuthn is available in this browser
 */
function webauthnIsAvailable() {
    return window.PublicKeyCredential !== undefined &&
           typeof window.PublicKeyCredential === 'function';
}

/**
 * Check if the platform has a built-in authenticator (biometrics)
 * Returns a Promise resolving to boolean
 */
function webauthnCheckPlatformAuth() {
    if (!webauthnIsAvailable()) {
        return Promise.resolve(false);
    }
    return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
}

// ============================================================
// Registration (after login, from Settings page)
// ============================================================

/**
 * Register a new biometric credential for the logged-in user
 * @param {string} csrfToken - CSRF token from the page
 * @param {string} credentialName - User-friendly name for the device
 * @returns {Promise}
 */
function webauthnRegister(csrfToken, credentialName) {
    credentialName = credentialName || 'My Device';

    // Step 1: Get registration options from server
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);

    return fetch('../api/webauthn/register_options.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (!data.success) {
            throw new Error(data.message || 'Failed to get registration options');
        }

        var options = data.options;
        var publicKey = options.publicKey || options;

        // Decode binary fields
        publicKey.challenge = decodeBinaryValue(publicKey.challenge);
        publicKey.user.id = decodeBinaryValue(publicKey.user.id);

        // Decode excludeCredentials if present
        if (publicKey.excludeCredentials) {
            publicKey.excludeCredentials = publicKey.excludeCredentials.map(function(cred) {
                cred.id = decodeBinaryValue(cred.id);
                return cred;
            });
        }

        // Step 2: Create credential via browser API (triggers biometric prompt)
        return navigator.credentials.create({ publicKey: publicKey });
    })
    .then(function(credential) {
        // Step 3: Send attestation to server for verification
        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('clientDataJSON', bufferToBase64url(credential.response.clientDataJSON));
        formData.append('attestationObject', bufferToBase64url(credential.response.attestationObject));
        formData.append('credential_name', credentialName);

        return fetch('../api/webauthn/register.php', {
            method: 'POST',
            body: formData
        });
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (!data.success) {
            throw new Error(data.message || 'Registration failed');
        }
        return data;
    });
}

// ============================================================
// Authentication (from login page)
// ============================================================

/**
 * Authenticate with biometrics
 * @param {string} username - The username to authenticate
 * @param {string} csrfToken - CSRF token from the page
 * @returns {Promise}
 */
function webauthnLogin(username, csrfToken) {
    // Step 1: Get authentication options from server
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('username', username);

    return fetch('../api/webauthn/login_options.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (!data.success) {
            throw new Error(data.message || 'Failed to get authentication options');
        }

        var options = data.options;
        var publicKey = options.publicKey || options;

        // Decode binary fields
        publicKey.challenge = decodeBinaryValue(publicKey.challenge);

        // Decode allowCredentials
        if (publicKey.allowCredentials) {
            publicKey.allowCredentials = publicKey.allowCredentials.map(function(cred) {
                cred.id = decodeBinaryValue(cred.id);
                return cred;
            });
        }

        // Step 2: Get assertion via browser API (triggers biometric prompt)
        return navigator.credentials.get({ publicKey: publicKey });
    })
    .then(function(assertion) {
        // Step 3: Send assertion to server for verification
        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('clientDataJSON', bufferToBase64url(assertion.response.clientDataJSON));
        formData.append('authenticatorData', bufferToBase64url(assertion.response.authenticatorData));
        formData.append('signature', bufferToBase64url(assertion.response.signature));
        formData.append('credentialId', bufferToBase64url(assertion.rawId));

        return fetch('../api/webauthn/login.php', {
            method: 'POST',
            body: formData
        });
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (!data.success) {
            throw new Error(data.message || 'Authentication failed');
        }
        return data;
    });
}
