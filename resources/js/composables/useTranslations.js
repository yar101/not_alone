import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useTranslations() {
    const page = usePage();

    const locale = computed(() => page.props.locale);
    const translations = computed(() => page.props.locale?.translations || {});

    const __ = (key, replacements = {}) => {
        let translation = translations.value[key] ?? key;

        Object.keys(replacements).forEach((placeholder) => {
            translation = translation.replace(
                new RegExp(`:${placeholder}`, 'g'),
                replacements[placeholder],
            );
        });

        return translation;
    };

    const transChoice = (key, count, replacements = {}) => {
        const translation = translations.value[key] ?? key;

        if (!translation.includes('|')) {
            return __(key, { ...replacements, count });
        }

        const forms = translation.split('|');

        let selected;

        if (locale.value?.current === 'ru') {
            const mod10 = Math.abs(count) % 10;
            const mod100 = Math.abs(count) % 100;

            if (mod100 >= 11 && mod100 <= 14) {
                selected = forms[2] ?? forms[0];
            } else if (mod10 === 1) {
                selected = forms[0];
            } else if (mod10 >= 2 && mod10 <= 4) {
                selected = forms[1] ?? forms[0];
            } else {
                selected = forms[2] ?? forms[0];
            }
        } else {
            selected = Math.abs(count) === 1 ? forms[0] : (forms[1] ?? forms[0]);
        }

        selected = selected.replace(/:count/g, count);

        Object.keys(replacements).forEach((placeholder) => {
            selected = selected.replace(
                new RegExp(`:${placeholder}`, 'g'),
                replacements[placeholder],
            );
        });

        return selected;
    };

    const switchLocale = (newLocale) => {
        if (locale.value?.available?.[newLocale]) {
            router.post('/locale', { locale: newLocale }, {
                preserveState: false,
                preserveScroll: true,
            });
        }
    };

    return { locale, __, transChoice, switchLocale };
}
