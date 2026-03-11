// ============================================
// CAMERA MODULE
// Camera stream, photo capture, GPS metadata, file upload validation
// ============================================

// ============================================
// IMAGE COMPRESSION UTILITY
// ============================================

function compressImage(file, maxWidth, quality) {
    maxWidth = maxWidth || 2000;
    quality = quality || 0.7;

    return new Promise(function(resolve) {
        if (!file.type.startsWith('image/')) {
            resolve(file);
            return;
        }

        var img = new Image();
        img.onload = function() {
            var canvas = document.createElement('canvas');
            var width = img.width;
            var height = img.height;

            if (width > maxWidth) {
                height = Math.round((height * maxWidth) / width);
                width = maxWidth;
            }

            canvas.width = width;
            canvas.height = height;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob(function(blob) {
                if (blob && blob.size < file.size) {
                    var compressed = new File([blob], file.name, { type: 'image/jpeg' });
                    resolve(compressed);
                } else {
                    resolve(file);
                }
            }, 'image/jpeg', quality);
        };
        img.onerror = function() {
            resolve(file);
        };
        img.src = URL.createObjectURL(file);
    });
}

let cameraStream = null;
let currentFacingMode = 'environment';
let patientCameraStream = null;
let patientCurrentFacingMode = 'environment';

// ============================================
// ENDORSEMENT CAMERA FUNCTIONS
// ============================================

function initializeCameraButton() {
    const openCameraBtn = document.getElementById('openCameraBtn');
    if (openCameraBtn) {
        openCameraBtn.addEventListener('click', function() {
            openCamera();
        });
    }
}

function openCamera() {
    const cameraContainer = document.getElementById('cameraContainer');
    const video = document.getElementById('cameraVideo');
    const controlsContainer = document.querySelector('.endorsement-doc-controls');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        Notiflix.Notify.failure('Camera access is not supported in your browser. Please use a modern browser or upload a file instead.');
        return;
    }

    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: currentFacingMode,
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        }
    })
    .then(function(stream) {
        cameraStream = stream;
        video.srcObject = stream;

        if (isMobileDevice()) {
            cameraContainer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            cameraContainer.offsetHeight; // force reflow for animation
            cameraContainer.classList.add('camera-active');
        } else {
            cameraContainer.style.display = 'block';
        }

        if (controlsContainer) controlsContainer.style.display = 'none';
    })
    .catch(function(error) {
        console.error('Error accessing camera:', error);
        let errorMessage = 'Unable to access camera. ';

        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            errorMessage += 'Please allow camera permissions in your browser settings.';
        } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            errorMessage += 'No camera device found. If using a PC, please connect a webcam or use the file upload option.';
        } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            errorMessage += 'Camera is already in use by another application.';
        } else {
            errorMessage += 'Error: ' + error.message;
        }

        Notiflix.Notify.failure(errorMessage);
    });
}

function capturePhoto() {
    const flashOverlay = document.getElementById('endorsementFlashOverlay');
    if (flashOverlay) {
        flashOverlay.classList.add('flash-active');
        setTimeout(function() { flashOverlay.classList.remove('flash-active'); }, 150);
    }

    const shutterBtn = document.getElementById('captureBtn');
    if (shutterBtn) {
        shutterBtn.classList.add('capturing');
        setTimeout(function() { shutterBtn.classList.remove('capturing'); }, 300);
    }

    const video = document.getElementById('cameraVideo');
    const preview = document.getElementById('attachmentPreview');
    const previewContainer = document.getElementById('previewContainer');
    const controlsContainer = document.querySelector('.endorsement-doc-controls');
    const fileUpload = document.getElementById('fileUpload');

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(function(blob) {
        const file = new File([blob], `endorsement_${Date.now()}.jpg`, { type: 'image/jpeg' });

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileUpload.files = dataTransfer.files;

        const imageUrl = URL.createObjectURL(blob);
        preview.src = imageUrl;
        previewContainer.style.display = 'block';
        if (controlsContainer) controlsContainer.style.display = 'none';

        closeCamera();

        Notiflix.Notify.success('Photo captured successfully!');
    }, 'image/jpeg', 0.9);
}

function closeCamera() {
    const cameraContainer = document.getElementById('cameraContainer');
    const video = document.getElementById('cameraVideo');
    const controlsContainer = document.querySelector('.endorsement-doc-controls');
    const previewContainer = document.getElementById('previewContainer');

    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }

    video.srcObject = null;

    if (isMobileDevice() && cameraContainer.classList.contains('camera-active')) {
        cameraContainer.classList.remove('camera-active');
        setTimeout(function() {
            cameraContainer.style.display = 'none';
            document.body.style.overflow = '';
        }, 350);
    } else {
        cameraContainer.style.display = 'none';
    }

    if (previewContainer && previewContainer.style.display !== 'block') {
        if (controlsContainer) controlsContainer.style.display = 'flex';
    }

    currentFacingMode = 'environment';
}

function validateFileUpload(input) {
    const file = input.files[0];
    const uploadError = document.getElementById('uploadError');
    const errorText = uploadError.querySelector('span');
    const preview = document.getElementById('attachmentPreview');
    const previewContainer = document.getElementById('previewContainer');
    const controlsContainer = document.querySelector('.endorsement-doc-controls');

    if (!file) return;

    const maxSize = 20 * 1024 * 1024;
    if (file.size > maxSize) {
        if (errorText) errorText.textContent = 'File size exceeds 20MB limit.';
        uploadError.style.display = 'flex';
        input.value = '';
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        if (errorText) errorText.textContent = 'Invalid file format. Allowed: JPG, PNG, GIF, WebP';
        uploadError.style.display = 'flex';
        input.value = '';
        return;
    }

    uploadError.style.display = 'none';

    compressImage(file, 2000, 0.7).then(function(compressed) {
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(compressed);
        input.files = dataTransfer.files;

        var imageUrl = URL.createObjectURL(compressed);
        preview.src = imageUrl;
        previewContainer.style.display = 'block';
        if (controlsContainer) controlsContainer.style.display = 'none';
    });
}

function removeAttachment() {
    const fileUpload = document.getElementById('fileUpload');
    const preview = document.getElementById('attachmentPreview');
    const previewContainer = document.getElementById('previewContainer');
    const controlsContainer = document.querySelector('.endorsement-doc-controls');

    fileUpload.value = '';
    preview.src = '';
    previewContainer.style.display = 'none';
    if (controlsContainer) controlsContainer.style.display = 'flex';
}

// Endorsement Image Modal Functions
function openEndorsementImageModal() {
    const modal = document.getElementById('endorsementImageModal');
    const modalImage = document.getElementById('endorsementModalImage');
    const preview = document.getElementById('attachmentPreview');

    if (modal && modalImage && preview && preview.src) {
        modalImage.src = preview.src;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeEndorsementImageModal() {
    const modal = document.getElementById('endorsementImageModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function openExistingEndorsementImageModal(imageUrl) {
    const modal = document.getElementById('endorsementImageModal');
    const modalImage = document.getElementById('endorsementModalImage');

    if (modal && modalImage && imageUrl) {
        modalImage.src = imageUrl;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// ============================================
// PATIENT DOCUMENTATION CAMERA FUNCTIONS
// ============================================

function initializePatientCameraButton() {
    const openPatientCameraBtn = document.getElementById('openPatientCameraBtn');
    if (openPatientCameraBtn) {
        openPatientCameraBtn.addEventListener('click', function() {
            openPatientCamera();
        });
    }
}

function openPatientCamera() {
    const cameraContainer = document.getElementById('patientCameraContainer');
    const video = document.getElementById('patientCameraVideo');
    const controlsContainer = document.querySelector('.patient-doc-controls');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        Notiflix.Notify.failure('Camera access is not supported in your browser. Please use a modern browser or upload a file instead.');
        return;
    }

    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: patientCurrentFacingMode,
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        }
    })
    .then(function(stream) {
        patientCameraStream = stream;
        video.srcObject = stream;

        if (isMobileDevice()) {
            cameraContainer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            cameraContainer.offsetHeight;
            cameraContainer.classList.add('camera-active');
        } else {
            cameraContainer.style.display = 'block';
        }

        if (controlsContainer) controlsContainer.style.display = 'none';
    })
    .catch(function(error) {
        console.error('Error accessing camera:', error);
        let errorMessage = 'Unable to access camera. ';

        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            errorMessage += 'Please allow camera permissions in your browser settings.';
        } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            errorMessage += 'No camera device found. If using a PC, please connect a webcam or use the file upload option.';
        } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            errorMessage += 'Camera is already in use by another application.';
        } else {
            errorMessage += 'Error: ' + error.message;
        }

        Notiflix.Notify.failure(errorMessage);
    });
}

function capturePatientPhoto() {
    const flashOverlay = document.getElementById('patientFlashOverlay');
    if (flashOverlay) {
        flashOverlay.classList.add('flash-active');
        setTimeout(function() { flashOverlay.classList.remove('flash-active'); }, 150);
    }

    const shutterBtn = document.getElementById('capturePatientBtn');
    if (shutterBtn) {
        shutterBtn.classList.add('capturing');
        setTimeout(function() { shutterBtn.classList.remove('capturing'); }, 300);
    }

    const video = document.getElementById('patientCameraVideo');
    const preview = document.getElementById('patientAttachmentPreview');
    const previewContainer = document.getElementById('patientPreviewContainer');
    const controlsContainer = document.querySelector('.patient-doc-controls');
    const fileUpload = document.getElementById('patientFileUpload');

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(function(originalBlob) {
        closePatientCamera();

        updateGPSStatus('fetching');

        captureAndStampGPS(originalBlob).then(function(result) {
            const finalBlob = result.blob;

            const file = new File([finalBlob], `patient_${Date.now()}.jpg`, { type: 'image/jpeg' });

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileUpload.files = dataTransfer.files;

            const imageUrl = URL.createObjectURL(finalBlob);
            preview.src = imageUrl;
            previewContainer.style.display = 'block';
            if (controlsContainer) controlsContainer.style.display = 'none';

            if (result.hasGPS) {
                updateGPSStatus('success');
            } else {
                updateGPSStatus('unavailable');
            }

            Notiflix.Notify.success('Photo captured successfully!');
        });
    }, 'image/jpeg', 0.9);
}

function closePatientCamera() {
    const cameraContainer = document.getElementById('patientCameraContainer');
    const video = document.getElementById('patientCameraVideo');
    const controlsContainer = document.querySelector('.patient-doc-controls');
    const previewContainer = document.getElementById('patientPreviewContainer');

    if (patientCameraStream) {
        patientCameraStream.getTracks().forEach(track => track.stop());
        patientCameraStream = null;
    }

    video.srcObject = null;

    if (isMobileDevice() && cameraContainer.classList.contains('camera-active')) {
        cameraContainer.classList.remove('camera-active');
        setTimeout(function() {
            cameraContainer.style.display = 'none';
            document.body.style.overflow = '';
        }, 350);
    } else {
        cameraContainer.style.display = 'none';
    }

    if (previewContainer && previewContainer.style.display !== 'block') {
        if (controlsContainer) controlsContainer.style.display = 'flex';
    }

    patientCurrentFacingMode = 'environment';
}

// ============================================
// CAMERA FLIP (FRONT/BACK) FUNCTIONS
// ============================================

function flipPatientCamera() {
    const flipBtn = document.getElementById('flipPatientCameraBtn');
    if (flipBtn) {
        flipBtn.classList.add('flipping');
        setTimeout(function() { flipBtn.classList.remove('flipping'); }, 400);
    }

    patientCurrentFacingMode = (patientCurrentFacingMode === 'environment') ? 'user' : 'environment';

    if (patientCameraStream) {
        patientCameraStream.getTracks().forEach(function(track) { track.stop(); });
    }

    const video = document.getElementById('patientCameraVideo');
    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: patientCurrentFacingMode,
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        }
    })
    .then(function(stream) {
        patientCameraStream = stream;
        video.srcObject = stream;
    })
    .catch(function(error) {
        console.error('Error flipping camera:', error);
        Notiflix.Notify.failure('Unable to switch camera. Your device may only have one camera.');
        patientCurrentFacingMode = (patientCurrentFacingMode === 'environment') ? 'user' : 'environment';
    });
}

function flipEndorsementCamera() {
    const flipBtn = document.getElementById('flipEndorsementCameraBtn');
    if (flipBtn) {
        flipBtn.classList.add('flipping');
        setTimeout(function() { flipBtn.classList.remove('flipping'); }, 400);
    }

    currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';

    if (cameraStream) {
        cameraStream.getTracks().forEach(function(track) { track.stop(); });
    }

    const video = document.getElementById('cameraVideo');
    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: currentFacingMode,
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        }
    })
    .then(function(stream) {
        cameraStream = stream;
        video.srcObject = stream;
    })
    .catch(function(error) {
        console.error('Error flipping camera:', error);
        Notiflix.Notify.failure('Unable to switch camera. Your device may only have one camera.');
        currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
    });
}

function validatePatientFileUpload(input) {
    const file = input.files[0];
    const uploadError = document.getElementById('patientUploadError');
    const errorText = uploadError.querySelector('span');
    const preview = document.getElementById('patientAttachmentPreview');
    const previewContainer = document.getElementById('patientPreviewContainer');
    const controlsContainer = document.querySelector('.patient-doc-controls');

    if (!file) return;

    const maxSize = 20 * 1024 * 1024;
    if (file.size > maxSize) {
        if (errorText) errorText.textContent = 'File size exceeds 20MB limit.';
        uploadError.style.display = 'flex';
        input.value = '';
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        if (errorText) errorText.textContent = 'Invalid file format. Allowed: JPG, PNG, GIF, WebP';
        uploadError.style.display = 'flex';
        input.value = '';
        return;
    }

    uploadError.style.display = 'none';

    updateGPSStatus('fetching');

    compressImage(file, 2000, 0.7).then(function(compressed) {
        var compressedBlob = compressed.slice(0, compressed.size, compressed.type);
        return captureAndStampGPS(compressedBlob).then(function(result) {
            var finalBlob = result.blob;

            var stampedFile = new File([finalBlob], file.name, { type: 'image/jpeg' });
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(stampedFile);
            input.files = dataTransfer.files;

            var imageUrl = URL.createObjectURL(finalBlob);
            preview.src = imageUrl;
            previewContainer.style.display = 'block';
            if (controlsContainer) controlsContainer.style.display = 'none';

            if (result.hasGPS) {
                updateGPSStatus('success');
            } else {
                updateGPSStatus('unavailable');
            }
        });
    });
}

function removePatientAttachment() {
    const fileUpload = document.getElementById('patientFileUpload');
    const preview = document.getElementById('patientAttachmentPreview');
    const previewContainer = document.getElementById('patientPreviewContainer');
    const controlsContainer = document.querySelector('.patient-doc-controls');

    fileUpload.value = '';
    preview.src = '';
    previewContainer.style.display = 'none';
    if (controlsContainer) controlsContainer.style.display = 'flex';

    clearPhotoMetadata();
}

// Patient Image Modal Functions
function openPatientImageModal() {
    const modal = document.getElementById('patientImageModal');
    const modalImage = document.getElementById('patientModalImage');
    const preview = document.getElementById('patientAttachmentPreview');

    if (modal && modalImage && preview && preview.src) {
        modalImage.src = preview.src;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closePatientImageModal() {
    const modal = document.getElementById('patientImageModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function openExistingPatientImageModal(imageUrl) {
    const modal = document.getElementById('patientImageModal');
    const modalImage = document.getElementById('patientModalImage');

    if (modal && modalImage && imageUrl) {
        modalImage.src = imageUrl;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// Close modals and cameras with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePatientImageModal();
        closeEndorsementImageModal();
        if (patientCameraStream) closePatientCamera();
        if (cameraStream) closeCamera();
    }
});

// ============================================
// GPS PHOTO METADATA & OVERLAY FUNCTIONS
// ============================================

function getGPSLocation() {
    return new Promise(function(resolve) {
        if (!navigator.geolocation) {
            resolve(null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(position) {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                });
            },
            function() {
                resolve(null);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }
        );
    });
}

function reverseGeocode(lat, lng) {
    var url = 'https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lng + '&format=json&addressdetails=1&zoom=18';

    return fetch(url, {
        headers: { 'Accept-Language': 'en' }
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data && data.address) {
            var addr = data.address;
            var city = addr.city || addr.town || addr.municipality || addr.village || addr.hamlet || '';
            var state = addr.state || addr.province || addr.region || '';
            var country = addr.country || '';
            var road = addr.road || addr.street || '';
            var suburb = addr.suburb || addr.neighbourhood || addr.quarter || '';
            var postcode = addr.postcode || '';

            var addressParts = [road, suburb, city, state, postcode, country].filter(function(p) { return p; });
            var fullAddress = addressParts.join(', ');

            var locationParts = [city, state, country].filter(function(p) { return p; });
            var locationName = locationParts.join(', ');

            return {
                locationName: locationName,
                fullAddress: fullAddress,
                country: country
            };
        }
        return null;
    })
    .catch(function() {
        return null;
    });
}

function captureAndStampGPS(originalBlob) {
    var captureTime = new Date();

    return getGPSLocation().then(function(gps) {
        if (!gps) {
            return stampGPSOverlay(originalBlob, {
                locationName: 'Location unavailable',
                fullAddress: '',
                latitude: null,
                longitude: null,
                datetime: captureTime
            }).then(function(stampedBlob) {
                populateMetadataFields(null, null, '', captureTime);
                return { blob: stampedBlob, hasGPS: false };
            });
        }

        return reverseGeocode(gps.latitude, gps.longitude).then(function(address) {
            var metadata = {
                locationName: address ? address.locationName : '',
                fullAddress: address ? address.fullAddress : '',
                latitude: gps.latitude,
                longitude: gps.longitude,
                datetime: captureTime
            };

            return stampGPSOverlay(originalBlob, metadata).then(function(stampedBlob) {
                populateMetadataFields(gps.latitude, gps.longitude, address ? address.fullAddress : '', captureTime);
                return { blob: stampedBlob, hasGPS: true };
            });
        });
    });
}

function stampGPSOverlay(imageBlob, metadata) {
    return new Promise(function(resolve) {
        var img = new Image();
        img.onload = function() {
            var canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            var ctx = canvas.getContext('2d');

            ctx.drawImage(img, 0, 0);

            var fontSize = Math.max(14, Math.round(img.width / 50));
            var lineHeight = fontSize * 1.4;
            var padding = Math.round(fontSize * 0.8);

            var lines = [];
            if (metadata.locationName) {
                lines.push(metadata.locationName);
            }
            if (metadata.fullAddress && metadata.fullAddress !== metadata.locationName) {
                var maxChars = Math.floor(img.width / (fontSize * 0.55));
                if (metadata.fullAddress.length > maxChars) {
                    var words = metadata.fullAddress.split(', ');
                    var currentLine = '';
                    for (var w = 0; w < words.length; w++) {
                        var testLine = currentLine ? currentLine + ', ' + words[w] : words[w];
                        if (testLine.length > maxChars && currentLine) {
                            lines.push(currentLine);
                            currentLine = words[w];
                        } else {
                            currentLine = testLine;
                        }
                    }
                    if (currentLine) lines.push(currentLine);
                } else {
                    lines.push(metadata.fullAddress);
                }
            }
            if (metadata.latitude !== null && metadata.longitude !== null) {
                lines.push('Lat ' + metadata.latitude.toFixed(6) + ', Long ' + metadata.longitude.toFixed(6));
            }
            if (metadata.datetime) {
                lines.push(formatGPSDateTime(metadata.datetime));
            }

            var overlayHeight = (lines.length * lineHeight) + (padding * 2);

            var overlayY = img.height - overlayHeight;
            ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
            ctx.fillRect(0, overlayY, img.width, overlayHeight);

            var accentWidth = Math.max(3, Math.round(fontSize * 0.25));
            ctx.fillStyle = '#3b82f6';
            ctx.fillRect(padding * 0.5, overlayY + padding * 0.5, accentWidth, overlayHeight - padding);

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold ' + fontSize + 'px Arial, sans-serif';
            ctx.textBaseline = 'top';

            var textX = padding + accentWidth + padding * 0.5;
            for (var i = 0; i < lines.length; i++) {
                var textY = overlayY + padding + (i * lineHeight);
                if (i === 0 && metadata.locationName) {
                    ctx.font = 'bold ' + Math.round(fontSize * 1.1) + 'px Arial, sans-serif';
                } else {
                    ctx.font = fontSize + 'px Arial, sans-serif';
                }

                ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
                ctx.shadowBlur = 2;
                ctx.shadowOffsetX = 1;
                ctx.shadowOffsetY = 1;
                ctx.fillText(lines[i], textX, textY);
            }

            ctx.shadowColor = 'transparent';
            ctx.shadowBlur = 0;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 0;

            canvas.toBlob(function(blob) {
                resolve(blob);
            }, 'image/jpeg', 0.9);
        };
        img.src = URL.createObjectURL(imageBlob);
    });
}

function populateMetadataFields(lat, lng, address, datetime) {
    var latField = document.getElementById('photoLatitude');
    var lngField = document.getElementById('photoLongitude');
    var addrField = document.getElementById('photoAddress');
    var dtField = document.getElementById('photoDatetime');

    if (latField) latField.value = lat !== null ? lat : '';
    if (lngField) lngField.value = lng !== null ? lng : '';
    if (addrField) addrField.value = address || '';
    if (dtField && datetime) {
        var y = datetime.getFullYear();
        var m = String(datetime.getMonth() + 1).padStart(2, '0');
        var d = String(datetime.getDate()).padStart(2, '0');
        var h = String(datetime.getHours()).padStart(2, '0');
        var min = String(datetime.getMinutes()).padStart(2, '0');
        var s = String(datetime.getSeconds()).padStart(2, '0');
        dtField.value = y + '-' + m + '-' + d + ' ' + h + ':' + min + ':' + s;
    } else if (dtField) {
        dtField.value = '';
    }
}

function clearPhotoMetadata() {
    var latField = document.getElementById('photoLatitude');
    var lngField = document.getElementById('photoLongitude');
    var addrField = document.getElementById('photoAddress');
    var dtField = document.getElementById('photoDatetime');

    if (latField) latField.value = '';
    if (lngField) lngField.value = '';
    if (addrField) addrField.value = '';
    if (dtField) dtField.value = '';

    updateGPSStatus('hidden');
}

function updateGPSStatus(state) {
    var statusEl = document.getElementById('gpsStatus');
    if (!statusEl) return;

    switch (state) {
        case 'fetching':
            statusEl.style.display = 'flex';
            statusEl.className = 'gps-status-indicator fetching';
            statusEl.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> <span>Getting location...</span>';
            break;
        case 'success':
            statusEl.style.display = 'flex';
            statusEl.className = 'gps-status-indicator success';
            statusEl.innerHTML = '<i class="bi bi-geo-alt-fill"></i> <span>Location captured</span>';
            break;
        case 'unavailable':
            statusEl.style.display = 'flex';
            statusEl.className = 'gps-status-indicator unavailable';
            statusEl.innerHTML = '<i class="bi bi-geo-alt"></i> <span>Location unavailable</span>';
            break;
        case 'hidden':
        default:
            statusEl.style.display = 'none';
            statusEl.innerHTML = '';
            break;
    }
}
