<?php

/**
 * License Modal Component
 * Include this in your login page for modal license activation
 */

$hardwareId = isset($auth) && $auth->getLicenseHelper() ? $auth->getLicenseHelper()->generateHardwareId() : 'N/A';
?>

<!-- License Activation Modal -->
<div id="licenseModal" class="license-modal" style="display: none;">
    <div class="license-modal-overlay" onclick="closeLicenseModal()"></div>
    <div class="license-modal-card">


        <!-- Header -->
        <div class="license-header">
            <div class="license-logo">
                <img src="assets/images/logo.png" alt="Logo" class="license-logo-image">
                <h1 class="license-title">Meine Firma Finance</h1>
            </div>
            <p class="license-subtitle">Systemlizenz aktivieren</p>
        </div>

        <!-- Form -->
        <form id="licenseForm" onsubmit="submitLicense(event)">
            <div class="form-group">
                <label class="form-label" for="modal_license_key">Lizenzschlüssel</label>
                <input type="text"
                    class="form-input"
                    id="modal_license_key"
                    name="license_key"
                    placeholder="KFZ####-####-####-####"
                    style="text-transform: uppercase; font-family: 'Courier New', monospace;"
                    required>
            </div>

            <!-- Hardware Info Box -->
            <div class="demo-info">
                <div class="demo-info-title">
                    Hardware-ID
                </div>
                <div class="hardware-id-value">
                    <?= htmlspecialchars($hardwareId) ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <button type="submit" class="btn btn-full">
                Lizenz aktivieren
            </button>

            <button type="button" class="btn btn-secondary btn-full" style="margin-top: 10px;" onclick="closeLicenseModal()">
                Abbrechen
            </button>
        </form>

        <!-- Error/Success Messages -->
        <div id="license-error" class="alert alert-error" style="display: none;"></div>
        <div id="license-success" class="alert alert-success" style="display: none;"></div>

        <div id="admin-only-message" class="alert alert-warning" style="display: none;">
            <i class="fas fa-lock"></i> Nur Administratoren können die Systemlizenz aktivieren
        </div>

        <div id="license-already-active" class="alert alert-success" style="display: none;">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Systemlizenz ist bereits aktiv!</strong><br>
                <span style="font-size: 12px;">Sie werden automatisch weitergeleitet...</span>
            </div>
        </div>


        <!-- Footer -->
        <div class="form-footer">
            <p>Diese Lizenz gilt für die gesamte Installation</p>
        </div>
    </div>
</div>

<style>
    /* License Modal - Styled like Login Form */
    .license-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-tonal-a0) 100%);
    }

    .license-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }

    /* Modal Card - Exactly like login-card */
    .license-modal-card {
        position: relative;
        background-color: var(--clr-surface-a10);
        border: 1px solid var(--clr-surface-a20);
        border-radius: 12px;
        padding: 40px;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        animation: licenseModalSlideIn 0.3s ease-out;
    }

    @keyframes licenseModalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    /* Header - Like login-header */
    .license-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .license-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .license-logo-image {
        width: 45px;
        height: 45px;
        object-fit: contain;
        filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.3));
    }

    .license-title {
        color: var(--clr-primary-a20);
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }

    .license-subtitle {
        color: var(--clr-surface-a50);
        font-size: 14px;
    }

    /* Alerts in Modal */
    .license-modal-card .alert {
        margin-top: 10px;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .license-modal-card .alert-error {
        background-color: rgba(248, 113, 113, 0.1);
        border: 1px solid #f87171;
        color: #fca5a5;
    }

    .license-modal-card .alert-success {
        background-color: rgba(74, 222, 128, 0.1);
        border: 1px solid #4ade80;
        color: #86efac;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .license-modal-card .alert-success i {
        font-size: 20px;
        color: #4ade80;
    }

    .license-modal-card .alert-warning {
        background-color: rgba(251, 191, 36, 0.1);
        border: 1px solid #fbbf24;
        color: #fcd34d;
    }

    /* Hardware ID Box - Styled like demo-info */
    .license-modal-card .demo-info {
        background: var(--clr-surface-tonal-a10);
        border: 1px solid var(--clr-primary-a20);
        border-radius: 6px;
        padding: 12px;
        margin: 20px 0;
        font-size: 13px;
    }

    .license-modal-card .demo-info-title {
        color: var(--clr-primary-a20);
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .hardware-id-value {
        font-family: 'Courier New', monospace;
        color: var(--clr-surface-a50);
        font-size: 12px;
        word-break: break-all;
        text-align: center;
        padding: 8px;
        background: var(--clr-surface-a0);
        border-radius: 4px;
        margin-top: 8px;
    }

    /* Close button X in top right corner */
    .license-modal-card::after {
        content: '×';
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 28px;
        color: var(--clr-surface-a40);
        cursor: pointer;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .license-modal-card:hover::after {
        background: var(--clr-surface-a20);
        color: var(--clr-primary-a20);
    }

    /* Mobile Responsive */
    @media (max-width: 480px) {
        .license-modal-card {
            width: 95%;
            margin: 10px;
            padding: 30px 20px;
        }

        .license-logo {
            flex-direction: column;
            gap: 8px;
        }

        .license-title {
            font-size: 1.3rem;
        }
    }
</style>

<script>
    function openLicenseModal(isAdmin = false, licenseActive = false) {
        const modal = document.getElementById('licenseModal');
        const adminMsg = document.getElementById('admin-only-message');
        const activeMsg = document.getElementById('license-already-active');
        const form = document.getElementById('licenseForm');

        // Reset all messages
        adminMsg.style.display = 'none';
        activeMsg.style.display = 'none';

        if (licenseActive) {
            // License is already active - show success message
            activeMsg.style.display = 'block';
            form.style.display = 'none';
        } else if (!isAdmin) {
            // User is not admin and license not active - show error
            adminMsg.style.display = 'block';
            form.style.display = 'none';
        } else {
            // Admin can activate license
            adminMsg.style.display = 'none';
            activeMsg.style.display = 'none';
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

    // Close on click of X button
    document.addEventListener('click', function(e) {
        if (e.target.matches('.license-modal-card::after')) {
            closeLicenseModal();
        }
    });

    // Close modal when clicking the pseudo-element X
    document.addEventListener('DOMContentLoaded', function() {
        const modalCard = document.querySelector('.license-modal-card');
        if (modalCard) {
            modalCard.addEventListener('click', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                // Check if click is in top-right corner area (X button)
                if (x > rect.width - 50 && y < 50) {
                    closeLicenseModal();
                }
            });
        }
    });

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
                    successDiv.style.display = 'block';
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    errorDiv.textContent = data.error || 'Lizenz ungültig';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                errorDiv.textContent = 'Verbindungsfehler. Bitte versuchen Sie es erneut.';
                errorDiv.style.display = 'block';
            });
    }

    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLicenseModal();
        }
    });
</script>