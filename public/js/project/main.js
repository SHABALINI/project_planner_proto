function toggleArea(areaId) {
    const body = document.getElementById(`area-body-${areaId}`);
    const toggle = document.getElementById(`area-toggle-${areaId}`);
    if (!body) return;
    
    body.classList.toggle('open');
    if (toggle) toggle.classList.toggle('collapsed');
    saveAreaState();
}

function toggleTask(taskId) {
    const body = document.getElementById(`task-body-${taskId}`);
    if (!body) return;
    
    if (body.style.display === 'none' || body.style.display === '') {
        body.style.display = 'block';
        setTimeout(() => {
            const container = document.getElementById(`comments-container-${taskId}`);
            if (container && container.children.length > 0) {
                container.scrollTop = container.scrollHeight;
            }
        }, 100);
    } else {
        body.style.display = 'none';
    }
}

function createArea(projectId) {
    const titleInput = document.getElementById(`areaTitle-${projectId}`);
    const descInput = document.getElementById(`areaDesc-${projectId}`);
    if (!titleInput || !titleInput.value.trim()) {
        showToast('Введите название области', 'warning');
        return;
    }
    
    fetch('/dashboard/area/create', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ 
            project_id: projectId, 
            title: titleInput.value, 
            description: descInput ? descInput.value : '' 
        }) 
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Область создана!', 'success');
            location.reload();
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Ошибка при создании области', 'error');
    });
}

function saveAreaState() {
    const states = {};
    document.querySelectorAll('.area-body').forEach(body => {
        const areaId = body.id.replace('area-body-', '');
        states[areaId] = body.classList.contains('open');
    });
    localStorage.setItem(`areaStates_${window.projectId}`, JSON.stringify(states));
}

function restoreAreaState() {
    const saved = localStorage.getItem(`areaStates_${window.projectId}`);
    if (!saved) return;
    
    const states = JSON.parse(saved);
    Object.keys(states).forEach(areaId => {
        const body = document.getElementById(`area-body-${areaId}`);
        const toggle = document.getElementById(`area-toggle-${areaId}`);
        if (body && states[areaId]) {
            body.classList.add('open');
            if (toggle) toggle.classList.remove('collapsed');
        }
    });
}

function initCommentForms() {
    document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
        form.removeEventListener('submit', handleCommentSubmit);
        form.addEventListener('submit', handleCommentSubmit);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const firstAreaBody = document.querySelector('.area-body');
    if (firstAreaBody && !localStorage.getItem(`areaStates_${window.projectId}`)) {
        firstAreaBody.classList.add('open');
        const firstToggle = document.querySelector('.area-toggle');
        if (firstToggle) firstToggle.classList.remove('collapsed');
    }
    
    restoreAreaState();
    refreshMembersPanel();
    
    document.querySelectorAll('form[action*="api_comment_create"]').forEach(form => {
        form.removeEventListener('submit', handleCommentSubmit);
        form.addEventListener('submit', handleCommentSubmit);
    });
    
    const searchInput = document.getElementById('userSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchText = e.target.value.toLowerCase();
            document.querySelectorAll('.user-search-item').forEach(item => {
                const email = item.getAttribute('data-email');
                if (email && email.includes(searchText)) {
                    item.classList.remove('d-none');
                } else {
                    item.classList.add('d-none');
                }
            });
        });
    }

    initCommentForms();

    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        const forms = node.querySelectorAll ? node.querySelectorAll('form[data-ajax="true"]') : [];
                        if (forms.length) {
                            initCommentForms();
                        }
                    }
                });
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

const originalToggleArea = toggleArea;
toggleArea = function(areaId) {
    originalToggleArea(areaId);
    saveAreaState();
};