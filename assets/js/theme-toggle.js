/**
 * Theme Toggle Handler
 * Handles switching between Light Mode and Dark Mode, updating icons, and persisting choice.
 */
document.addEventListener('DOMContentLoaded', function () {
  function getStoredTheme() {
    return localStorage.getItem('jobfind_theme');
  }

  function getSystemTheme() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function getCurrentTheme() {
    return getStoredTheme() || getSystemTheme();
  }

  function updateToggleButtons(theme) {
    const buttons = document.querySelectorAll('.theme-toggle-btn');
    buttons.forEach(function (btn) {
      const isDark = theme === 'dark';
      const icon = btn.querySelector('.theme-toggle-icon') || btn.querySelector('i');
      const text = btn.querySelector('.theme-toggle-text');

      if (icon) {
        icon.className = isDark ? 'bi bi-sun-fill theme-toggle-icon' : 'bi bi-moon-stars-fill theme-toggle-icon';
      }

      if (text) {
        text.textContent = isDark ? 'โหมดสว่าง' : 'โหมดมืด';
      }

      btn.setAttribute('title', isDark ? 'สลับเป็นโหมดสว่าง' : 'สลับเป็นโหมดมืด');
      btn.setAttribute('aria-label', isDark ? 'สลับเป็นโหมดสว่าง' : 'สลับเป็นโหมดมืด');
    });
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-bs-theme', theme);
    updateToggleButtons(theme);
  }

  function toggleTheme() {
    var current = document.documentElement.getAttribute('data-theme') || getCurrentTheme();
    var nextTheme = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem('jobfind_theme', nextTheme);

    // Animate the icon spin on toggle
    var buttons = document.querySelectorAll('.theme-toggle-btn .theme-toggle-icon');
    buttons.forEach(function (icon) {
      icon.style.transform = 'rotate(360deg) scale(0.5)';
      icon.style.opacity = '0.3';
    });

    // Add smooth body transition class
    document.documentElement.style.transition = 'background .4s ease, color .4s ease';
    document.body.style.transition = 'background .4s ease, color .4s ease';

    setTimeout(function () {
      applyTheme(nextTheme);

      buttons.forEach(function (icon) {
        icon.style.transform = '';
        icon.style.opacity = '';
      });

      // Remove the inline transition after animation completes
      setTimeout(function () {
        document.documentElement.style.transition = '';
        document.body.style.transition = '';
      }, 450);
    }, 150);
  }

  // Initial sync of button UI
  applyTheme(getCurrentTheme());

  // Delegate click events for all toggle buttons
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.theme-toggle-btn');
    if (btn) {
      e.preventDefault();
      toggleTheme();
    }
  });

  // Listen for system preference changes if no manual preference is saved
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
      if (!getStoredTheme()) {
        applyTheme(e.matches ? 'dark' : 'light');
      }
    });
  }
});
