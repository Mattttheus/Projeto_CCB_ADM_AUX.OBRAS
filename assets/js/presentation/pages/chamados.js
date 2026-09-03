// Presentation/Pages — equivalente a page/cadastrar_suporte.php e page/fechar_chamado.php
import { layout, panel, badge, escapeHtml } from '../ui.js';

const PRIORITY_TONE = { Alta: 'atrasada', Urgente: 'atrasada', Normal: 'pendente' };
const STATUS_KEY = { Aberto: 'aberto', 'Em análise': 'em_analise' };

export function renderChamados(ctx) {
    const items = ctx.store.state.chamados;
    return layout('Chamados', 'Acompanhe solicitações e ocorrências da equipe.',
        '<button class="button button-primary" data-route-link="suporte">+ Abrir chamado</button>')
        + panel('Chamados', `<div class="data-list">${items.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                <div class="row-meta">Prioridade ${escapeHtml(item.priority)}${item.description ? ` · ${escapeHtml(item.description)}` : ''}</div></div>
                ${badge(STATUS_KEY[item.status] ?? 'pendente', item.status)}
                ${item.status !== 'Fechado' ? `<button class="table-action" data-close-ticket="${item.id}">Fechar</button>` : ''}
            </div>`).join('') || '<div class="empty-state">Nenhum chamado registrado.</div>'}</div>`);
}

export function renderSuporte() {
    return layout('Abrir chamado', 'Envie uma solicitação para o time responsável.')
        + panel('Nova solicitação', `
            <form id="support-form" class="form-grid">
                <div class="form-field full"><label for="support-title">Assunto</label><input class="field" id="support-title" name="titulo" required></div>
                <div class="form-field"><label for="support-priority">Prioridade</label>
                    <select class="field" id="support-priority" name="prioridade"><option>Normal</option><option>Alta</option><option>Urgente</option></select></div>
                <div class="form-field full"><label for="support-description">Descrição</label><textarea class="field" id="support-description" name="descricao" rows="5" required></textarea></div>
                <div class="form-actions full"><button class="button button-primary" type="submit">Enviar chamado</button></div>
            </form>`);
}

export function bindSuporte(ctx) {
    document.querySelector('#support-form').addEventListener('submit', event => {
        event.preventDefault();
        const title = document.querySelector('#support-title').value.trim();
        if (!title) return;
        ctx.store.state.chamados.push({
            id: ctx.store.nextId(ctx.store.state.chamados),
            title,
            priority: document.querySelector('#support-priority').value,
            description: document.querySelector('#support-description').value.trim(),
            status: 'Aberto',
        });
        ctx.store.log(`Chamado "${title}" aberto.`);
        ctx.store.save();
        ctx.navigate('chamados');
        ctx.notify('Chamado aberto.');
    });
}
