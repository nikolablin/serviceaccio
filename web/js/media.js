// ===== НАСТРОЙКИ =====
const MediaToast = {
    messages: {
        copySuccess: 'Ссылка скопирована',
        deleteSuccess: 'Файл удалён',
        error: 'Произошла ошибка'
    },
    delay: 2000
};

// ===== TOAST =====
function showMediaToast(message) {
    const toastEl = document.getElementById('copyToast');
    if (!toastEl) return;

    const body = toastEl.querySelector('.toast-body');
    if (body) {
        body.textContent = message;
    }

    const toast = bootstrap.Toast.getOrCreateInstance(toastEl, {
        delay: MediaToast.delay
    });

    toast.show();
}

// ===== КОПИРОВАНИЕ ССЫЛКИ =====
$(document).on('click', '.copy-link', function (e) {
    e.preventDefault();

    const url = $(this).data('url');
    if (!url) return;

    const $icon = $(this);

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url)
            .then(() => {
                highlight($icon);
                showMediaToast(MediaToast.messages.copySuccess);
            })
            .catch(() => {
                showMediaToast(MediaToast.messages.error);
            });
    } else {
        // fallback
        const $tmp = $('<input>');
        $('body').append($tmp);
        $tmp.val(url).select();
        document.execCommand('copy');
        $tmp.remove();

        highlight($icon);
        showMediaToast(MediaToast.messages.copySuccess);
    }
});

// ===== ПОДСВЕТКА ИКОНКИ =====
function highlight($el) {
    $el.addClass('text-success');
    setTimeout(() => {
        $el.removeClass('text-success');
    }, 800);
}
