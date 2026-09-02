@if (session('success'))
    <div class="flash-message flash-message--success" role="status" aria-live="polite">
        <span>{{ session('success') }}</span>
        <button type="button" class="flash-message__close" aria-label="Cerrar notificación">&times;</button>
    </div>
@endif

@if (session('error'))
    <div class="flash-message flash-message--error" role="alert" aria-live="assertive">
        <span>{{ session('error') }}</span>
        <button type="button" class="flash-message__close" aria-label="Cerrar notificación">&times;</button>
    </div>
@endif

<div id="toast-region" class="toast-region" aria-live="polite" aria-atomic="true"></div>

@once
    <script>
        document.addEventListener('click', function (event) {
            var closeButton = event.target.closest('.flash-message__close');
            if (closeButton) {
                closeButton.closest('.flash-message').remove();
            }
        });

        window.showToast = function (message, type) {
            var region = document.getElementById('toast-region');
            if (!region) return;

            var toast = document.createElement('div');
            toast.className = 'toast toast--' + (type || 'success');
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
            toast.textContent = message;
            region.appendChild(toast);

            window.setTimeout(function () {
                toast.remove();
            }, 4500);
        };
    </script>
@endonce
