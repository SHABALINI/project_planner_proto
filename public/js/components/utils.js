// public/js/components/utils.js

// МОДАЛЬНОЕ ОКНО ДЛЯ ИЗОБРАЖЕНИЙ 
function openImageModal(imageUrl) {
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
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeImageModal();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeImageModal();
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

window.openImageModal = openImageModal;
window.closeImageModal = closeImageModal;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getElement(id) {
    const el = document.getElementById(id);
    if (!el) console.warn(`Element not found: #${id}`);
    return el;
}

function setText(id, text) {
    const el = getElement(id);
    if (el) el.textContent = text;
}