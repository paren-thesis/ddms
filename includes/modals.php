<?php
/**
 * HTU COMPSSA DDMS - Shared Modals
 * Centralized confirmation dialogs for a consistent UI
 */
?>

<!-- Universal Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="confirmModalLabel">
                    <i class="fas fa-question-circle me-2 text-primary"></i>Confirm Action
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p id="confirmModalMessage" class="mb-0 fs-5 text-center"></p>
            </div>
            <div class="modal-footer border-0 pb-4 justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" id="confirmModalCancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="confirmModalConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Custom Confirmation Dialog
 * @param {Object} options - { title, message, confirmBtnText, confirmBtnClass, onConfirm }
 */
function showConfirm(options) {
    const modalEl = document.getElementById('confirmModal');
    const modal = new bootstrap.Modal(modalEl);
    
    // Set content
    document.getElementById('confirmModalLabel').innerHTML = `<i class="fas fa-question-circle me-2"></i>${options.title || 'Confirm Action'}`;
    document.getElementById('confirmModalMessage').textContent = options.message || 'Are you sure you want to proceed?';
    
    const confirmBtn = document.getElementById('confirmModalConfirmBtn');
    confirmBtn.textContent = options.confirmBtnText || 'Confirm';
    confirmBtn.className = `btn ${options.confirmBtnClass || 'btn-primary'} px-4`;
    
    // Clear previous event listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    newConfirmBtn.addEventListener('click', function() {
        if (typeof options.onConfirm === 'function') {
            options.onConfirm();
        }
        modal.hide();
    });
    
    modal.show();
}

/**
 * Handle Logout with Custom Modal
 */
function confirmLogout(logoutUrl) {
    showConfirm({
        title: 'Logout Confirmation',
        message: 'Are you sure you want to log out of the system?',
        confirmBtnText: 'Logout',
        confirmBtnClass: 'btn-danger',
        onConfirm: function() {
            window.location.href = logoutUrl;
        }
    });
    return false;
}
</script>
