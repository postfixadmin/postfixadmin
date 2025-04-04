document.addEventListener('DOMContentLoaded', function() {
    // Apply theme immediately before page rendering completes
    applyThemeFromStorage();
    
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return; // Exit if no theme toggle button found

    const themeText = themeToggle.querySelector('.theme-text');
    const darkThemeCss = document.getElementById('dark-theme-css');
    
    // Create sun and moon icons with inline SVG
    const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" class="theme-icon sun-icon" style="margin-right: 8px;">
        <path fill="currentColor" d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 0a.5.5 0 0 1-.707 0L11.536 13.05a.5.5 0 0 1 .707.707l1.414-1.414a.5.5 0 0 1 0-.707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>
    </svg>`;
    
    const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" class="theme-icon moon-icon" style="margin-right: 8px;">
        <path fill="currentColor" d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>
    </svg>`;
    
    // Update icon container - find existing or create new
    let iconContainer = themeToggle.querySelector('.theme-icon-container');
    if (!iconContainer) {
        iconContainer = document.createElement('span');
        iconContainer.className = 'theme-icon-container';
        if (themeText) {
            themeToggle.insertBefore(iconContainer, themeText);
        } else {
            themeToggle.prepend(iconContainer);
        }
    }

    // Function to set theme
    function setTheme(theme) {
        // Set data-theme attribute on html element to match CSS selectors
        document.documentElement.setAttribute('data-theme', theme);
        
        if (theme === 'dark') {
            if (darkThemeCss) {
                darkThemeCss.disabled = false;
            }
            if (themeText) {
                themeText.textContent = 'Light Mode';
            }
            if (themeToggle) {
                themeToggle.classList.add('btn-inverse');
            }
            // Update icon to sun (to switch to light mode)
            if (iconContainer) {
                iconContainer.innerHTML = sunIcon;
            }
        } else {
            if (darkThemeCss) {
                darkThemeCss.disabled = true;
            }
            if (themeText) {
                themeText.textContent = 'Dark Mode';
            }
            if (themeToggle) {
                themeToggle.classList.remove('btn-inverse');
            }
            // Update icon to moon (to switch to dark mode)
            if (iconContainer) {
                iconContainer.innerHTML = moonIcon;
            }
        }
        localStorage.setItem('theme-preference', theme);
    }
    
    // Apply theme from localStorage or system preference
    function applyThemeFromStorage() {
        const savedTheme = localStorage.getItem('theme-preference');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            const darkThemeCss = document.getElementById('dark-theme-css');
            if (darkThemeCss) {
                darkThemeCss.disabled = savedTheme !== 'dark';
            }
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
            const darkThemeCss = document.getElementById('dark-theme-css');
            if (darkThemeCss) {
                darkThemeCss.disabled = false;
            }
        }
    }

    // Update theme toggle button state based on current theme
    const currentTheme = document.documentElement.getAttribute('data-theme');
    if (currentTheme === 'dark') {
        if (themeText) {
            themeText.textContent = 'Light Mode';
        }
        themeToggle.classList.add('btn-inverse');
        if (iconContainer) {
            iconContainer.innerHTML = sunIcon;
        }
    } else {
        if (themeText) {
            themeText.textContent = 'Dark Mode';
        }
        if (iconContainer) {
            iconContainer.innerHTML = moonIcon;
        }
    }

    // Listen for theme toggle click
    themeToggle.addEventListener('click', function(e) {
        e.preventDefault();
        const currentTheme = document.documentElement.getAttribute('data-theme');
        setTheme(currentTheme === 'dark' ? 'light' : 'dark');
    });

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme-preference')) {
            setTheme(e.matches ? 'dark' : 'light');
        }
    });
}); 