document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return; // Exit if no theme toggle button found

    const themeText = themeToggle.querySelector('.theme-text');
    const darkThemeCss = document.getElementById('dark-theme-css');

    // Function to set theme
    function setTheme(theme) {
        if (theme === 'dark') {
            if (darkThemeCss) {
                darkThemeCss.disabled = false;
            }
            document.body.classList.add('dark-theme');
            if (themeText) {
                themeText.textContent = 'Light Mode';
            }
        } else {
            if (darkThemeCss) {
                darkThemeCss.disabled = true;
            }
            document.body.classList.remove('dark-theme');
            if (themeText) {
                themeText.textContent = 'Dark Mode';
            }
        }
        localStorage.setItem('theme-preference', theme);

        // Update button appearance based on theme
        if (themeToggle) {
            themeToggle.classList.toggle('btn-inverse', theme === 'dark');
        }
    }

    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme-preference');
    if (savedTheme) {
        setTheme(savedTheme);
    } else {
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            setTheme('dark');
        } else {
            setTheme('light');
        }
    }

    // Listen for theme toggle click
    themeToggle.addEventListener('click', function(e) {
        e.preventDefault();
        const isDark = document.body.classList.contains('dark-theme');
        setTheme(isDark ? 'light' : 'dark');
    });

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme-preference')) {
            setTheme(e.matches ? 'dark' : 'light');
        }
    });
}); 