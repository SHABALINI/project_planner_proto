document.addEventListener('DOMContentLoaded', function() {
    const avatarInput = document.getElementById('avatarInput');
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                showToast(`${AppIcons.get('x', 'icon-danger')} Файл слишком большой. Максимум 5MB`, 'error');
                this.value = '';
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showToast(`${AppIcons.get('x', 'icon-danger')} Неподдерживаемый формат. Разрешены: JPEG, PNG, GIF, WEBP`, 'error');
                this.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('avatar', file);

            const btn = document.querySelector('[onclick*="avatarInput"]');
            const originalText = btn ? btn.textContent : 'Загрузить';
            if (btn) {
                btn.innerHTML = `${AppIcons.get('wait', 'icon-sm icon-spin')} Загрузка...`;
                btn.disabled = true;
            }

            const url = avatarInput.dataset.uploadUrl || window.uploadAvatarUrl;

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Server response:', text);
                    throw new Error('Server returned invalid JSON');
                }
            })
            .then(data => {
                if (btn) {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }

                if (data.success) {
                    const preview = document.getElementById('avatarPreview');
                    if (preview) {
                        preview.src = data.avatar + '?t=' + Date.now();
                    } else {
                        const wrapper = document.querySelector('.avatar-wrapper');
                        if (wrapper) {
                            const placeholder = wrapper.querySelector('.avatar-placeholder');
                            if (placeholder) placeholder.remove();
                            const img = document.createElement('img');
                            img.id = 'avatarPreview';
                            img.className = 'avatar-image';
                            img.src = data.avatar + '?t=' + Date.now();
                            wrapper.appendChild(img);
                        }
                    }
                    showToast('Аватар обновлен!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(`${AppIcons.get('x', 'icon-danger')} Ошибка: ` + (data.error || 'Неизвестная ошибка'), 'error');
                }
            })
            .catch(err => {
                if (btn) {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
                console.error('Error:', err);
                showToast(`${AppIcons.get('x', 'icon-danger')} Ошибка при загрузке аватара: ` + err.message, 'error');
            });
        });
    }

    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const data = {
                fullName: document.getElementById('fullName')?.value || '',
                company: document.getElementById('company')?.value || '',
                position: document.getElementById('position')?.value || '',
                university: document.getElementById('university')?.value || '',
                specialty: document.getElementById('specialty')?.value || '',
                educationLevel: document.getElementById('educationLevel')?.value || '',
                bio: document.getElementById('bio')?.value || '',
                telegram: document.getElementById('telegram')?.value || '',
                github: document.getElementById('github')?.value || '',
                linkedin: document.getElementById('linkedin')?.value || '',
                website: document.getElementById('website')?.value || ''
            };

            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn ? btn.textContent : 'Сохранить';
            if (btn) {
                btn.innerHTML = `${AppIcons.get('wait', 'icon-sm icon-spin')} Сохранение...`;
                btn.disabled = true;
            }

            const url = profileForm.dataset.updateUrl || window.updateProfileUrl;

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', text);
                    throw new Error('Server returned invalid response');
                }
            })
            .then(data => {
                if (btn) {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }

                if (data.success) {
                    showToast('Профиль обновлен!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(`${AppIcons.get('x', 'icon-danger')} Ошибка: ` + (data.error || 'Неизвестная ошибка'), 'error');
                }
            })
            .catch(err => {
                if (btn) {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
                console.error('Error:', err);
                showToast(`${AppIcons.get('x', 'icon-danger')} Ошибка при сохранении профиля: ` + err.message, 'error');
            });
        });
    }
});