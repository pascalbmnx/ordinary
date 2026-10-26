// HelpKonnect — i18n (English / Kiswahili)
window.HNC_LANG = (function () {
    const translations = {
        en: {
            app_name: 'HelpKonnect',
            tagline: 'Get electrical application support, drawings, information, and direct assistance.',
            home: 'Home',
            apply_now: 'Apply Now',
            request_drawing: 'Request Drawing',
            get_assistance: 'Get Assistance',
            contact_admin: 'Contact Admin',
            track_request: 'Track Request',
            docs_library: 'Documents',
            faq: 'FAQ',
            announcements: 'Announcements',
            services: 'Our Services',
            how_it_works: 'How It Works',
            contact: 'Contact Us',
            our_services: 'Our Services',
            new_electricity: 'New Electricity Applications',
            drawings: 'Application Drawings',
            assistance: 'Technical Assistance',
            support: 'Support & Help',
            footer_rights: 'All rights reserved.',
            search: 'Search...',
            loading: 'Loading...',
            something_wrong: 'Something went wrong. Please try again.',
            no_results: 'No results found.',
            read_more: 'Read more',
            latest_announcements: 'Recent Announcements',
            important_documents: 'Important Documents',
            view_all: 'View All',
            download: 'Download',
            services_desc: 'From new electricity applications to drawings and technical support, we help you connect with HelpKonnect services.',
        },
        sw: {
            app_name: 'HelpKonnect',
            tagline: 'Pata msaada wa maombi ya umeme, michoro, taarifa, na msaada wa moja kwa moja.',
            home: 'Nyumbani',
            apply_now: 'Omba Sasa',
            request_drawing: 'Omba Mchoro',
            get_assistance: 'Pata Msaada',
            contact_admin: 'Wasiliana na Admin',
            track_request: 'Fuatilia Ombi',
            docs_library: 'Nyaraka',
            faq: 'Maswali',
            announcements: 'Matangazo',
            services: 'Huduma Zetu',
            how_it_works: 'Jinsi Inavyofanya Kazi',
            contact: 'Wasiliana Nasi',
            our_services: 'Huduma Zetu',
            new_electricity: 'Maombi ya Umeme Mpya',
            drawings: 'Michoro ya Maombi',
            assistance: 'Msaada wa Kiufundi',
            support: 'Msaada na Usaidizi',
            footer_rights: 'Haki zote zimehifadhiwa.',
            search: 'Tafuta...',
            loading: 'Inapakia...',
            something_wrong: 'Kuna tatizo limetokea. Tafadhali jaribu tena.',
            no_results: 'Hakuna matokeo.',
            read_more: 'Soma zaidi',
            latest_announcements: 'Matangazo ya Hivi Karibuni',
            important_documents: 'Nyaraka Muhimu',
            view_all: 'Ona Yote',
            download: 'Pakua',
            services_desc: 'Kutoka kwa maombi ya umeme mpya hadi michoro na msaada wa kiufundi, tunakusaidia kuwasiliana na huduma za HelpKonnect.',
        },
    };

    let current = localStorage.getItem('hnc_lang') || 'en';

    function setLang(lang) {
        current = translations[lang] ? lang : 'en';
        localStorage.setItem('hnc_lang', current);
        apply();
        document.dispatchEvent(new CustomEvent('hnc:lang', { detail: current }));
    }

    function t(key) {
        return (translations[current] && translations[current][key]) || (translations.en[key] || key);
    }

    function isSw() { return current === 'sw'; }
    function getLang() { return current; }

    function apply() {
        // Apply data-i18n attributes
        document.querySelectorAll('[data-i18n]').forEach(el => {
            el.textContent = t(el.getAttribute('data-i18n'));
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            el.setAttribute('placeholder', t(el.getAttribute('data-i18n-placeholder')));
        });
        // Update language toggle state
        document.querySelectorAll('[data-lang-btn]').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-lang-btn') === current);
        });
    }

    globalThis.addEventListener('DOMContentLoaded', apply);

    return { t, setLang, apply, isSw, getLang };
})();
