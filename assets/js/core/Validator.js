// Core — espelha app/Core/Validator.php
export class ValidationError extends Error {
    constructor(message) {
        super(message);
        this.name = 'ValidationError';
    }
}

export const Validator = {
    email(value) {
        const email = String(value ?? '').trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            throw new ValidationError('Informe um e-mail válido.');
        }
        return email;
    },

    requiredText(value, field, maxLength = 255) {
        const text = String(value ?? '').trim();
        if (text === '' || text.length > maxLength) {
            throw new ValidationError(`Informe ${field} com até ${maxLength} caracteres.`);
        }
        return text;
    },

    oneOf(value, allowed, field) {
        const option = String(value ?? '');
        if (!allowed.includes(option)) {
            throw new ValidationError(`${field} inválido.`);
        }
        return option;
    },

    date(value) {
        const text = String(value ?? '');
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
        const parsed = match ? new Date(`${text}T00:00:00`) : null;
        if (!match || !parsed || Number.isNaN(parsed.getTime())
            || parsed.getMonth() + 1 !== Number(match[2])
            || parsed.getDate() !== Number(match[3])) {
            throw new ValidationError('Informe uma data válida.');
        }
        return text;
    },

    positiveNumber(value, field) {
        const number = Number(String(value ?? '').replace(',', '.'));
        if (!Number.isFinite(number) || number <= 0) {
            throw new ValidationError(`Informe ${field} válido(a).`);
        }
        return number;
    },

    nonNegativeNumber(value, field) {
        const number = Number(String(value ?? '').replace(',', '.'));
        if (!Number.isFinite(number) || number < 0) {
            throw new ValidationError(`Informe ${field} válido(a).`);
        }
        return number;
    },

    id(value) {
        const id = Number(value);
        if (!Number.isInteger(id) || id < 1) {
            throw new ValidationError('Identificador inválido.');
        }
        return id;
    },
};
