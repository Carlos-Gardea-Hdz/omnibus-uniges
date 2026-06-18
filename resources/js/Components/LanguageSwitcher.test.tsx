import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LocaleProvider } from '@/Contexts/LocaleContext';
import LanguageSwitcher from '@/Components/LanguageSwitcher';

describe('LanguageSwitcher', () => {
    it('renders an accessible, labelled toggle defaulting to ES', () => {
        render(
            <LocaleProvider>
                <LanguageSwitcher />
            </LocaleProvider>,
        );

        const button = screen.getByRole('button', { name: /idioma|language/i });
        expect(button).toHaveTextContent(/es/i);
    });

    it('switches the visible locale to EN on click', async () => {
        const user = userEvent.setup();
        render(
            <LocaleProvider>
                <LanguageSwitcher />
            </LocaleProvider>,
        );

        await user.click(screen.getByRole('button'));
        expect(screen.getByRole('button')).toHaveTextContent(/en/i);
    });
});
