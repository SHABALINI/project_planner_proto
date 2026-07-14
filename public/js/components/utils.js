// public/js/components/utils.js
//  ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ 

// Экранирование HTML
function escapeHtml(text) {
    return text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Получение элемента по ID с проверкой
function getElement(id) {
    const el = document.getElementById(id);
    if (!el) console.warn(`Element not found: #${id}`);
    return el;
}

// Безопасное обновление текста элемента
function setText(id, text) {
    const el = getElement(id);
    if (el) el.textContent = text;
}

// Парсинг чисел из строки
function parseIntSafe(value) {
    return parseInt(value) || 0;
}

// Обновление бейджа с уведомлениями
function updateBadge(id, count) {
    const el = getElement(id);
    if (el) {
        if (count > 0) {
            el.textContent = count;
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
    }
}