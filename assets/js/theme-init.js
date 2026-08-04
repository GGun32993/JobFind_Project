/**
 * Theme Initialization Script
 * Executes immediately to prevent Flash of Unstyled Content (FOUC).
 */
(function () {
  try {
    const savedTheme = localStorage.getItem('jobfind_theme');
    let theme = savedTheme;
    if (!theme) {
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      theme = prefersDark ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-bs-theme', theme);
  } catch (e) {
    console.error('Error initializing theme:', e);
  }
})();
