// Presentation — helpers de renderização compartilhados pelas páginas.
import { ACTIVITY_STATUS_LABELS } from '../domain/activity/ActivityStatus.js';
import { FINANCIAL_CATEGORY_LABELS } from '../domain/finance/FinancialCategory.js';
import { ROLE_LABELS } from '../core/Auth.js';

const LABELS = {
    ...ACTIVITY_STATUS_LABELS,
    ...FINANCIAL_CATEGORY_LABELS,
    ...ROLE_LABELS,
    liberado: 'Liberado',
    bloqueado: 'Bloqueado',
    aberto: 'Aberto',
    em_analise: 'Em análise',
    informativo: 'Informativo',
};

const TONES = {
    pendente: 'gray',
    em_andamento: 'orange',
    concluida: 'green',
    atrasada: 'red',
    admin: 'green',
    suporte: 'orange',
    colaborador: 'gray',
    liberado: 'green',
    bloqueado: 'red',
    aberto: 'red',
    em_analise: 'orange',
    informativo: 'gray',
};

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g,
        char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

export function money(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export function formatDate(value) {
    return new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR');
}

export function formatDateTime(value) {
    return new Date(value).toLocaleString('pt-BR');
}

export function badge(key, label = LABELS[key]) {
    const tone = TONES[key] ?? 'gray';
    return `<span class="badge badge-${tone}">${escapeHtml(label ?? key)}</span>`;
}

export function layout(title, subtitle, action = '') {
    return `<div class="page-header"><div><p class="eyebrow">Painel de controle</p><h1 class="page-title">${title}</h1><p class="page-subtitle">${subtitle}</p></div>${action}</div>`;
}

export function panel(title, body, extra = '') {
    return `<section class="panel ${extra}"><div class="panel-heading"><div><h2 class="panel-title">${title}</h2></div></div>${body}</section>`;
}

export function stat(label, value, note, icon, tone = '') {
    return `<article class="stat-card"><div class="stat-top"><span>${label}</span><span class="stat-icon">${icon}</span></div><div class="stat-value ${tone}">${value}</div><div class="stat-note">${note}</div></article>`;
}

export function notify(message) {
    const toast = document.querySelector('#toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2600);
}

export function closeModal() {
    document.querySelector('#modal')?.remove();
}

/**
 * Abre um modal de formulário. onSubmit recebe um objeto com os campos (name="...")
 * e pode lançar Error/ValidationError — a mensagem é exibida em toast e o modal
 * permanece aberto para correção (mesmo comportamento das validações do PHP).
 */
export function openModal(title, bodyHtml, onSubmit, onSuccess) {
    closeModal();
    document.body.insertAdjacentHTML('beforeend', `
        <div class="modal-backdrop" id="modal">
            <div class="modal">
                <div class="modal-head"><h2>${title}</h2><button class="close-button" type="button" data-action="close-modal" aria-label="Fechar">×</button></div>
                <form id="modal-form">${bodyHtml}<div class="form-actions"><button class="button button-primary" type="submit">Salvar</button></div></form>
            </div>
        </div>`);
    document.querySelector('#modal-form').addEventListener('submit', event => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.target).entries());
        try {
            onSubmit(data);
        } catch (error) {
            notify(error.message);
            return;
        }
        closeModal();
        onSuccess?.();
    });
}
