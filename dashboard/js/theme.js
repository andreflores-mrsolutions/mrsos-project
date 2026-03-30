document.addEventListener('DOMContentLoaded', () => {

            // aplicar tema inicial desde cookie
            const cookieTheme = document.cookie.match(/mrs_theme=([^;]+)/);
            const theme = cookieTheme ? decodeURIComponent(cookieTheme[1]) : 'light';

            applyTheme(theme, {
                saveCookie: false,
                saveStorage: false
            });

            // botón desktop
            const btnThemeDesktop = document.getElementById('btnThemeDesktop');
            if (btnThemeDesktop) {
                btnThemeDesktop.addEventListener('click', () => {
                    const current = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                    const next = current === 'dark' ? 'light' : 'dark';
                    applyTheme(next);
                });
            }

            // botón mobile
            const btnThemeMobile = document.getElementById('btnThemeMobile');
            if (btnThemeMobile) {
                btnThemeMobile.addEventListener('click', (e) => {
                    e.preventDefault();
                    const current = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                    const next = current === 'dark' ? 'light' : 'dark';
                    applyTheme(next);
                });
            }

        });

        function applyTheme(mode, {
            saveCookie = true,
            saveStorage = true
        } = {}) {
            const isDark = (mode === 'dark');

            // Clases en body (compatibilidad con estilos viejos y nuevos)
            document.body.classList.toggle('dark-mode', isDark);
            document.body.classList.toggle('mrsos-dark', isDark);

            // Cookie + localStorage
            if (saveCookie) {
                setCookie(THEME_COOKIE, isDark ? 'dark' : 'light');
            }
            if (saveStorage) {
                localStorage.setItem(DARK_KEY, isDark ? '1' : '0');
            }

            // Sincronizar iconos de los botones de tema (header)
            const deskBtn = document.getElementById('btnThemeDesktop');
            const mobBtn = document.getElementById('btnThemeMobile');

            if (deskBtn) {
                const i = deskBtn.querySelector('i');
                if (i) {
                    i.classList.remove('bi-moon', 'bi-moon-fill');
                    i.classList.add(isDark ? 'bi-moon-fill' : 'bi-moon');
                }
                deskBtn.title = isDark ? 'Modo claro' : 'Modo oscuro';
            }

            if (mobBtn) {
                const i = mobBtn.querySelector('i');
                const label = mobBtn.querySelector('span');
                if (i) {
                    i.classList.remove('bi-moon', 'bi-moon-fill');
                    i.classList.add(isDark ? 'bi-moon-fill' : 'bi-moon');
                }
                if (label) {
                    label.textContent = isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
                }
            }

            // Sincronizar switch de configuración
            const switchDark = document.getElementById('switchDarkMode');
            const labelDark = document.getElementById('labelDarkMode');
            if (switchDark) switchDark.checked = isDark;
            if (labelDark) labelDark.textContent = isDark ? 'Modo oscuro' : 'Modo claro';
        }