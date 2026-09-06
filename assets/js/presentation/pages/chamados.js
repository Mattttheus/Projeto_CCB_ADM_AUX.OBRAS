// Presentation/Pages — equivalente a page/cadastrar_suporte.php e page/fechar_chamado.php
// Prioridade e status usam os mesmos enums do banco (verde/amarelo/vermelho,
// aberto/em_atendimento/resolvido/fechado).
import { layout, panel, badge, escapeHtml, formatDateTime } from '../ui.js';

export function renderChamados(ctx) {
    const obraIds = new Set(ctx.store.obrasFor(ctx.auth.user()).map(obra => obra.id));
    const items = ctx.store.state.chamados
        .filter(item => item.obraId == null || obraIds.has(item.obraId) || item.userId === ctx.auth.user()?.id);
    const full = ctx.auth.hasFullProjectAccess();
    return layout('Chamados', 'Acompanhe solicitações e ocorrências da equipe.',
        '<button class="button button-primary" data-route-link="suporte">+ Abrir chamado</button>')
        + panel('Chamados', `<div class="data-list">${items.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                <div class="row-meta">${escapeHtml(ctx.store.obraName(item.obraId))} · ${badge(item.priority)}${item.description ? ` · ${escapeHtml(item.description)}` : ''}</div>
                ${item.date ? `<div class="row-meta">Aberto em ${formatDateTime(item.date)}</div>` : ''}</div>
                ${badge(item.status)}
                ${full && item.status === 'aberto' ? `<button class="table-action" data-chamado-status="${item.id}:em_atendimento">Atender</button>` : ''}
                ${item.status !== 'fechado' ? `<button class="table-action" data-chamado-status="${item.id}:fechado">Fechar</button>` : ''}
            </div>`).join('') || '<div class="empty-state">Nenhum chamado registrado.</div>'}</div>`);
}

export function renderSuporte(ctx) {
    const obras = ctx.store.obrasFor(ctx.auth.user());
    return layout('Abrir chamado', 'Envie uma solicitação para o time responsável.')
        + panel('Nova solicitação', `
            <form id="support-form" class="form-grid">
                <div class="form-field full"><label for="support-title">Assunto</label><input class="field" id="support-title" name="titulo" required></div>
                <div class="form-field"><label for="support-obra">Obra (opcional)</label>
                    <select class="field" id="support-obra" name="obra_id"><option value="">Nenhuma</option>
                    ${obras.map(obra => `<option value="${obra.id}">${escapeHtml(obra.name)}</option>`).join('')}</select></div>
                <div class="form-field"><label for="support-priority">Prioridade</label>
                    <select class="field" id="support-priority" name="prioridade"><option value="verde">Normal</option><option value="amarelo">Alta</option><option value="vermelho">Urgente</option></select></div>
                <div class="form-field full"><label for="support-description">Descrição</label><textarea class="field" id="support-description" name="descricao" rows="5" required></textarea></div>
                <div class="form-actions full"><button class="button button-primary" type="submit">Enviar chamado</button></div>
            </form>`);
}

export function bindSuporte(ctx) {
    document.querySelector('#support-form').addEventListener('submit', async event => {
        event.preventDefault();
        const button = event.target.querySelector('button[type="submit"]');
        const title = document.querySelector('#support-title').value.trim();
        if (!title) return;
        button.disabled = true;
        try {
            await ctx.store.addChamado({
                obraId: document.querySelector('#support-obra').value || null,
                title,
                description: document.querySelector('#support-description').value.trim(),
                priority: document.querySelector('#support-priority').value,
                userId: ctx.auth.user()?.id ?? null,
            });
            ctx.navigate('chamados');
            ctx.notify('Chamado aberto.');
        } catch (error) {
            ctx.notify(error.message);
            button.disabled = false;
        }
    });
}
