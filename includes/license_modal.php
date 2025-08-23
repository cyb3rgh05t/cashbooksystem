<?php

/**
 * License Modal Component
 * Include this in your login page for modal license activation
 */

$hardwareId = isset($auth) && $auth->getLicenseHelper() ? $auth->getLicenseHelper()->generateHardwareId() : 'N/A';
?>

<!-- License Activation Modal -->
<div id="licenseModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeLicenseModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-key"></i> Systemlizenz aktivieren</h2>
            <button class="modal-close" onclick="closeLicenseModal()">&times;</button>
        </div>

        <div class="modal-body">
            <div id="license-error" class="alert alert-error" style="display: none;"></div>
            <div id="license-success" class="alert alert-success" style="display: none;"></div>

            <div id="admin-only-message" class="alert alert-warning" style="display: none;">
                <i class="fas fa-lock"></i>
                Nur Administratoren können die Systemlizenz aktivieren.
            </div>

            <form id="licenseForm" onsubmit="submitLicense(event)">
                <div class="form-group">
                    <label for="modal_license_key">
                        <i class="fas fa-certificate"></i> Lizenzschlüssel
                    </label>
                    <input type="text"
                        id="modal_license_key"
                        name="license_key"
                        placeholder="KFZ####-####-####-####"
                        style="text-transform: uppercase;"
                        required>
                </div>

                <div class="hardware-info">
                    <div class="hardware-label">
                        <i class="fas fa-fingerprint"></i> Hardware-ID
                    </div>
                    <div class="hardware-value"><?= htmlspecialchars($hardwareId) ?></div>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Lizenz aktivieren
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeLicenseModal()">
                        Abbrechen
                    </button>
                </div>
            </form>

            <div class="info-box">
                <p><i class="fas fa-info-circle"></i> Diese Lizenz gilt für die gesamte Installation und alle Benutzer.</p>
            </div>
        </div>
    </div>
</div>

<style>
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
    }

    .modal-content {
        position: relative;
        background: var(--clr-dark-a0);
        border: 1px solid var(--clr-surface-a10);
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid var(--clr-surface-a10);
    }

    .modal-header h2 {
        margin: 0;
        color: var(--clr-primary-a0);
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--clr-surface-a50);
        font-size: 28px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: var(--clr-surface-a10);
        color: var(--clr-text);
    }

    .modal-body {
        padding: 24px;
    }

    .modal-body .form-group {
        margin-bottom: 20px;
    }

    .modal-body label {
        display: block;
        margin-bottom: 8px;
        color: var(--clr-surface-a70);
        font-weight: 500;
        font-size: 14px;
    }

    .modal-body input[type="text"] {
        width: 100%;
        padding: 12px 16px;
        background: var(--clr-surface-a05);
        border: 1px solid var(--clr-surface-a20);
        border-radius: 8px;
        color: var(--clr-text);
        font-size: 15px;
        transition: all 0.2s;
    }

    .modal-body input[type="text"]:focus {
        outline: none;
        border-color: var(--clr-primary-a0);
        background: var(--clr-surface-a10);
        box-shadow: 0 0 0 3px rgba(var(--clr-primary-rgb), 0.1);
    }

    .hardware-info {
        background: var(--clr-surface-a05);
        border: 1px solid var(--clr-surface-a10);
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 20px;
    }

    .hardware-label {
        color: var(--clr-surface-a50);
        font-size: 12px;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .hardware-value {
        font-family: 'Courier New', monospace;
        color: var(--clr-primary-a0);
        font-size: 13px;
        word-break: break-all;
        background: var(--clr-dark-a0);
        padding: 8px;
        border-radius: 4px;
        border: 1px solid var(--clr-surface-a10);
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }

    .modal-actions .btn {
        flex: 1;
        padding: 12px 20px;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .modal-actions .btn-primary {
        background: var(--clr-primary-a0);
        color: var(--clr-dark-a0);
    }

    .modal-actions .btn-primary:hover {
        background: var(--clr-primary-a10);
        transform: translateY(-1px);
    }

    .modal-actions .btn-secondary {
        background: var(--clr-surface-a10);
        color: var(--clr-surface-a70);
    }

    .modal-actions .btn-secondary:hover {
        background: var(--clr-surface-a20);
    }

    .modal .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .modal .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    .modal .alert-warning {
        background: rgba(251, 191, 36, 0.1);
        color: #fbbf24;
        border: 1px solid rgba(251, 191, 36, 0.2);
    }

    .modal .info-box {
        background: var(--clr-surface-a05);
        border: 1px solid var(--clr-surface-a10);
        border-radius: 8px;
        padding: 12px;
        margin-top: 16px;
    }

    .modal .info-box p {
        margin: 0;
        color: var(--clr-surface-a60);
        font-size: 13px;
    }

    @media (max-width: 600px) {
        .modal-content {
            width: 95%;
            margin: 10px;
        }

        .modal-actions {
            flex-direction: column;
        }
    }
</style>

<script>
    function openLicenseModal(isAdmin = false) {
        const modal = document.getElementById('licenseModal');
        const adminMsg = document.getElementById('admin-only-message');
        const form = document.getElementById('licenseForm');

        if (!isAdmin) {
            adminMsg.style.display = 'block';
            form.style.display = 'none';
        } else {
            adminMsg.style.display = 'none';
            form.style.display = 'block';
        }

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLicenseModal() {
        const modal = document.getElementById('licenseModal');
        modal.style.display = 'none';
        document.body.style.overflow = '';

        // Clear messages
        document.getElementById('license-error').style.display = 'none';
        document.getElementById('license-success').style.display = 'none';
    }

    function submitLicense(event) {
        event.preventDefault();

        const licenseKey = document.getElementById('modal_license_key').value;
        const errorDiv = document.getElementById('license-error');
        const successDiv = document.getElementById('license-success');

        // Clear previous messages
        errorDiv.style.display = 'none';
        successDiv.style.display = 'none';

        // AJAX Submit
        fetch('auth/validate_license.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'license_key=' + encodeURIComponent(licenseKey)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = 'Lizenz erfolgreich aktiviert! Weiterleitung...';
                    successDiv.style.display = 'flex';
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    errorDiv.textContent = data.error || 'Lizenz ungültig';
                    errorDiv.style.display = 'flex';
                }
            })
            .catch(error => {
                errorDiv.textContent = 'Verbindungsfehler. Bitte versuchen Sie es erneut.';
                errorDiv.style.display = 'flex';
            });
    }

    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLicenseModal();
        }
    });
</script>