export function detectTheme() {
    const value = getComputedStyle(document.documentElement).getPropertyValue('--dt-tw-theme').trim();
    return value === 'tailwind' ? 'tailwind' : null;
}
//# sourceMappingURL=detectTheme.js.map