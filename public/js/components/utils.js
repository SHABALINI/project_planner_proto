//  МОДАЛЬНОЕ ОКНО ДЛЯ ИЗОБРАЖЕНИЙ 
function openImageModal(imageUrl) {
    // Создаем модальное окно если его нет
    let modal = document.getElementById('imageModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'imageModal';
        modal.className = 'image-modal';
        modal.innerHTML = `
            <button class="image-modal-close" onclick="closeImageModal()">×</button>
            <img id="modalImage" src="" alt="Просмотр изображения">
        `;
        document.body.appendChild(modal);
        
        // Закрытие по клику на фон
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeImageModal();
            }
        });
        
        // Закрытие по ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    }
    
    const img = document.getElementById('modalImage');
    if (img) {
        img.src = imageUrl;
        img.alt = 'Просмотр изображения';
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Добавляем в глобальный скоуп
window.openImageModal = openImageModal;
window.closeImageModal = closeImageModal;

// Экранирование HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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