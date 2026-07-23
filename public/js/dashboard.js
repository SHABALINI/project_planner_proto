// public/js/dashboard.js

function createProject() {
    const titleInput = document.getElementById('projectTitle');
    if (!titleInput) return;

    const titleValue = titleInput.value.trim();
    if (!titleValue) {
        titleInput.focus();
        titleInput.style.borderColor = '#ef4444';
        setTimeout(() => titleInput.style.borderColor = '', 2000);
        return;
    }
    
    const btn = document.querySelector('.create-card .input-group button');
    const originalText = btn ? btn.textContent : '';
    if (btn) {
        btn.textContent = '⏳ Создание...';
        btn.disabled = true;
    }
    
    // Берём URL, переданный из Twig шаблона
    const createUrl = window.APP_ROUTES ? window.APP_ROUTES.projectCreate : '/dashboard/project/create';
    
    fetch(createUrl, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ title: titleValue }) 
    })
    .then(res => {
        if (!res.ok) throw new Error('Server error: ' + res.status);
        return res.json();
    })
    .then(data => {
        if (btn) {
            btn.textContent = originalText;
            btn.disabled = false;
        }
        
        if (data.success) {
            titleInput.value = '';
            location.reload();
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    })
    .catch(err => {
        if (btn) {
            btn.textContent = originalText;
            btn.disabled = false;
        }
        console.error('Error:', err);
        alert('Не удалось создать проект. Проверьте консоль.');
    });
}

function deleteElement(type, id, confirmMessage = null) {
    if (confirmMessage && !confirm(confirmMessage)) return;
    
    const card = document.getElementById(`project-card-${id}`);
    if (card) {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0.5';
        card.style.transform = 'scale(0.95)';
    }
    
    fetch(`/dashboard/delete/${type}/${id}`, { method: 'POST' })
    .then(res => res.json())
    .then(data => { 
        if (data.success) {
            location.reload();
        } else {
            alert('Ошибка: ' + (data.error || 'Не удалось удалить'));
            if (card) {
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            }
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Ошибка при удалении');
        if (card) {
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        }
    });
}

function togglePin(projectId) {
    const btn = document.querySelector(`#project-card-${projectId} .pin-btn`);
    if (btn) {
        btn.textContent = '⏳';
        btn.disabled = true;
    }
    
    fetch(`/dashboard/project/${projectId}/toggle-pin`, { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Ошибка: ' + (data.error || 'Не удалось закрепить проект'));
            if (btn) {
                btn.textContent = '📌';
                btn.disabled = false;
            }
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Ошибка при закреплении проекта');
        if (btn) {
            btn.textContent = '📌';
            btn.disabled = false;
        }
    });
}

// Анимация карточек при загрузке
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.project-card').forEach((card, i) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(16px)';
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 50 * i);
    });
});