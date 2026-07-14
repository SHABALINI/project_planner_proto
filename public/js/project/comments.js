// public/js/project/comments.js
//  КОММЕНТАРИИ 

function deleteComment(commentId) {
    if (!confirm('Вы действительно хотите удалить этот комментарий?')) return;
    
    const commentItem = document.getElementById(`comment-${commentId}`);
    if (commentItem) {
        commentItem.style.opacity = '0.5';
        commentItem.style.pointerEvents = 'none';
    }
    
    fetch(`/dashboard/comment/delete/${commentId}`, { method: 'POST' })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Комментарий удален!', 'success');
            if (commentItem) {
                commentItem.style.transition = 'all 0.3s ease';
                commentItem.style.transform = 'translateX(20px)';
                commentItem.style.opacity = '0';
                setTimeout(() => {
                    commentItem.remove();
                    const container = commentItem.closest('.comments-container');
                    if (container && container.children.length === 0) {
                        const emptyMsg = document.createElement('div');
                        emptyMsg.className = 'comments-empty';
                        emptyMsg.textContent = 'Нет комментариев';
                        container.appendChild(emptyMsg);
                    }
                }, 300);
            }
        } else {
            showToast('Ошибка: ' + (data.error || 'Не удалось удалить комментарий'), 'error');
            if (commentItem) {
                commentItem.style.opacity = '1';
                commentItem.style.pointerEvents = 'auto';
            }
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Ошибка при удалении комментария', 'error');
        if (commentItem) {
            commentItem.style.opacity = '1';
            commentItem.style.pointerEvents = 'auto';
        }
    });
}

function handleCommentSubmit(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const form = this;
    const formData = new FormData(form);
    const text = formData.get('text');
    const file = formData.get('file');
    
    if (!text.trim() && (!file || file.size === 0)) {
        showToast('Напишите комментарий или прикрепите файл', 'warning');
        return;
    }
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '⏳';
    submitBtn.disabled = true;
    
    fetch('/dashboard/comment/create', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => {
        if (!res.ok) {
            return res.text().then(text => {
                throw new Error('Server error: ' + res.status + ' - ' + text);
            });
        }
        return res.json();
    })
    .then(data => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            showToast('Комментарий отправлен!', 'success');
            
            const taskItem = form.closest('.task-item');
            if (!taskItem) return;
            
            let commentsContainer = taskItem.querySelector('.comments-container');
            if (!commentsContainer) {
                const containerDiv = document.createElement('div');
                containerDiv.className = 'comments-container';
                const parent = form.parentElement;
                parent.insertBefore(containerDiv, form);
                commentsContainer = containerDiv;
            }
            
            const emptyMsg = commentsContainer.querySelector('.text-muted');
            if (emptyMsg) emptyMsg.remove();
            
            const newComment = document.createElement('div');
            newComment.className = 'comment-item';
            newComment.id = `comment-${data.id}`;
            
            const now = new Date();
            const timeStr = now.toLocaleString('ru-RU', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
            
            let fileHtml = '';
            if (data.filePath) {
                fileHtml = `<a href="${data.filePath}" target="_blank" class="text-purple" style="font-size: 12px;">📎 ${data.fileName}</a>`;
            }
            
            const isAdmin = window.userRole === 'admin';
            const deleteBtn = isAdmin ? 
                `<button class="btn btn-sm text-danger ms-2" style="border: none; background: none; font-size: 12px;" onclick="deleteComment(${data.id})">✕</button>` : '';
            
            const safeText = text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            
            newComment.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span class="comment-author">👤 ${data.author}</span>
                    <div>
                        <span class="comment-time">${timeStr}</span>
                        ${deleteBtn}
                    </div>
                </div>
                <p class="comment-text">${safeText}</p>
                ${fileHtml}
            `;
            commentsContainer.appendChild(newComment);
            commentsContainer.scrollTop = commentsContainer.scrollHeight;
            
            setTimeout(() => {
                newComment.classList.remove('new-comment');
            }, 3000);
            
            form.querySelector('input[name="text"]').value = '';
            const fileInput = form.querySelector('input[type="file"]');
            if (fileInput) fileInput.value = '';
            form.querySelector('input[name="text"]').focus();
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        console.error('Error:', err);
        showToast('Ошибка при отправке комментария', 'error');
    });
}