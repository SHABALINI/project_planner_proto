// public/js/project/comments.js

function deleteComment(commentId) {
    if (!confirm('Вы действительно хотите удалить этот комментарий?')) return;
    
    const commentItem = document.getElementById(`comment-${commentId}`);
    if (commentItem) {
        commentItem.style.opacity = '0.5';
        commentItem.style.pointerEvents = 'none';
    }
    
    fetch(`/dashboard/comment/delete/${commentId}`, { method: 'POST' })
    .then(res => {
        if (!res.ok) {
            throw new Error('Server error: ' + res.status);
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Комментарий удален!', 'success');
            
            if (commentItem) {
                commentItem.style.transition = 'all 0.3s ease';
                commentItem.style.transform = 'translateX(20px)';
                commentItem.style.opacity = '0';
                setTimeout(() => {
                    commentItem.remove();
                    
                    // Проверяем, остались ли комментарии
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
                commentItem.style.transform = 'translateX(0)';
            }
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Ошибка при удалении комментария', 'error');
        if (commentItem) {
            commentItem.style.opacity = '1';
            commentItem.style.pointerEvents = 'auto';
            commentItem.style.transform = 'translateX(0)';
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
    const taskId = form.querySelector('input[name="task_id"]').value;
    
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
                throw new Error('Server error: ' + res.status + ' - ' + text.substring(0, 100));
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
            
            // Ищем или создаем контейнер для комментариев
            let commentsContainer = document.getElementById(`comments-container-${taskId}`);
            if (!commentsContainer) {
                commentsContainer = document.createElement('div');
                commentsContainer.className = 'comments-container';
                commentsContainer.id = `comments-container-${taskId}`;
                
                // Находим место для вставки
                const formParent = form.parentElement;
                const parent = formParent.parentElement;
                const commentsBlock = parent.querySelector('.mt-3.pt-3');
                if (commentsBlock) {
                    // Вставляем контейнер перед формой
                    commentsBlock.insertBefore(commentsContainer, formParent);
                } else {
                    // Если нет блока, создаем его
                    const newBlock = document.createElement('div');
                    newBlock.className = 'mt-3 pt-3';
                    newBlock.style.cssText = 'border-top: 1px solid #f0f0f0;';
                    newBlock.appendChild(commentsContainer);
                    const taskBody = form.closest('.subtask-list');
                    if (taskBody) {
                        taskBody.appendChild(newBlock);
                    }
                }
            }
            
            // Удаляем сообщение "Нет комментариев"
            const emptyMsg = commentsContainer.querySelector('.comments-empty');
            if (emptyMsg) emptyMsg.remove();
            
            // Создаем новый комментарий
            const newComment = document.createElement('div');
            newComment.className = 'comment-item new-comment';
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
            const currentUserEmail = window.currentUserEmail;
            const canDelete = isAdmin || data.author === currentUserEmail;
            
            const deleteBtn = canDelete ? 
                `<button class="btn btn-sm text-danger ms-2" style="border: none; background: none; font-size: 12px;" onclick="deleteComment(${data.id})">✕</button>` : '';
            
            const safeText = (text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            
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
            
            // Добавляем в конец контейнера (снизу)
            commentsContainer.appendChild(newComment);
            
            // Прокручиваем к новому комментарию
            setTimeout(() => {
                commentsContainer.scrollTop = commentsContainer.scrollHeight;
            }, 100);
            
            // Убираем подсветку через 3 секунды
            setTimeout(() => {
                newComment.classList.remove('new-comment');
            }, 3000);
            
            // Очищаем форму
            const textInput = form.querySelector('input[name="text"]');
            if (textInput) textInput.value = '';
            const fileInput = form.querySelector('input[type="file"]');
            if (fileInput) fileInput.value = '';
            
            // Фокусируем поле ввода
            if (textInput) textInput.focus();
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