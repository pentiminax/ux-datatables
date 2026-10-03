const catalogs = {
    'en-GB': () => import('../i18n/en-GB.js'),
    'fr-FR': () => import('../i18n/fr-FR.js'),
};
const CDN_LOCALE_PATTERN = /\/i18n\/([\w-]+)\.json(?:[?#].*)?$/;
export async function applyLocalLanguage(payload) {
    const loader = payload?.language ? loaderForUrl(payload.language.url) : loaderForLocale(payload);
    if (!loader) {
        return;
    }
    payload.language = { ...(await loader()).default };
}
function loaderForUrl(url) {
    if (typeof url !== 'string') {
        return undefined;
    }
    const locale = CDN_LOCALE_PATTERN.exec(url)?.[1];
    return locale ? catalogs[locale] : undefined;
}
function loaderForLocale(payload) {
    if (typeof payload?.locale !== 'string') {
        return undefined;
    }
    const language = payload.locale.split(/[-_]/)[0].toLowerCase();
    return Object.entries(catalogs).find(([key]) => key.split('-')[0].toLowerCase() === language)?.[1];
}
//# sourceMappingURL=localLanguage.js.map