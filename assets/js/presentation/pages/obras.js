// Presentation/Pages — equivalente a page/gerenciar_obra.php
// Inclui a designação de responsáveis pelo admin (obra_responsaveis):
// cada responsável de igreja passa a ver apenas as obras designadas a ele.
// O botão "Detalhes" consolida cronograma, financeiro, documentos e chamados
// da obra em um único painel — mesma ideia das abas do gerenciar_obra.php.
import { obraStatusLabel } from '../../domain/obra/ObraStatus.js';
import { displayStatus, ACTIVITY_STATUS_LABELS } from '../../domain/activity/ActivityStatus.js';
import { categoryLabel } from '../../domain/finance/FinancialCategory.js';
import { ROLE_LABELS } from '../../core/Auth.js';
import { layout, panel, badge, money, formatDate, formatDateTime, escapeHtml, openModal, openInfoModal, notify } from '../ui.js';

function responsaveisNames(ctx, obra) {
    const nomes = (obra.responsaveis ?? [])
        .map(id => ctx.store.state.users.find(user => user.id === Number(id)))
        .filter(Boolean)
        .map(user => `${escapeHtml(user.name)} <span class="row-meta">(${ROLE_LABELS[user.role] ?? user.role})</span>`);
    return nomes.length ? nomes.join(', ') : '<span class="row-meta">Sem responsável</span>';
}

function rows(ctx, items) {
    const isAdmin = ctx.auth.isAdmin();
    return items.length ? items.map(item => {
        const progress = ctx.store.obraProgress(item.id);
        return `
        <tr>
            <td><strong>${escapeHtml(item.name)}</strong><div class="row-meta">${escapeHtml(item.city)}</div></td>
            <td>${badge(item.status, obraStatusLabel(item.status))}</td>
            <td><div class="progress-track"><div class="progress-fill" style="width:${progress}%"></div></div><small>${progress}%</small></td>
            <td>${money(item.budget)}</td>
            <td>${responsaveisNames(ctx, item)}</td>
            <td>
                <button class="table-action" data-view="${item.id}">Detalhes</button>
                ${isAdmin ? `<button class="table-action" data-assign="${item.id}">Responsáveis</button>` : ''}
                ${isAdmin ? `<button class="table-action" data-delete="obra:${item.id}">Excluir</button>` : ''}
            </td>
        </tr>`;
    }).join('')
        : '<tr><td colspan="6"><div class="empty-state">Nenhuma obra encontrada.</div></td></tr>';
}

/** Painel único com cronograma, financeiro, documentos e chamados da obra — equivalente às abas do gerenciar_obra.php. */
function detailHtml(ctx, obra) {
    const activities = ctx.store.state.activities.filter(item => item.obraId === obra.id)
        .sort((a, b) => (a.date ?? '').localeCompare(b.date ?? ''));
    const transactions = ctx.store.state.transactions.filter(item => item.obraId === obra.id)
        .sort((a, b) => b.date.localeCompare(a.date));
    const documents = ctx.store.state.documents.filter(item => item.obraId === obra.id)
        .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''));
    const chamados = ctx.store.state.chamados.filter(item => item.obraId === obra.id)
        .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''));

    const spent = transactions.reduce((sum, item) => sum + item.quantity * item.unitCost, 0);
    const progress = ctx.store.obraProgress(obra.id);

    return `
        <div class="list-row">
            <div class="row-main">
                <div class="row-title">${badge(obra.status, obraStatusLabel(obra.status))} Progresso do cronograma: <strong>${progress}%</strong></div>
                <div class="row-meta">Orçamento: <strong>${money(obra.budget)}</strong> · Gasto: <strong>${money(spent)}</strong> · Saldo: <strong>${money(obra.budget - spent)}</strong></div>
            </div>
        </div>
        <h3 class="detail-heading">Cronograma <button class="table-action" data-action="new-activity" data-preset-obra="${obra.id}">+ Atividade</button></h3>
        <div class="data-list">${activities.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                <div class="row-meta">Prazo ${formatDate(item.date)}${item.description ? ` · ${escapeHtml(item.description)}` : ''}</div></div>
                ${badge(displayStatus(item), ACTIVITY_STATUS_LABELS[displayStatus(item)])}
            </div>`).join('') || '<div class="empty-state">Nenhuma atividade cadastrada.</div>'}</div>

        <h3 class="detail-heading">Financeiro <button class="table-action" data-action="new-transaction" data-preset-obra="${obra.id}">+ Lançamento</button></h3>
        <div class="data-list">${transactions.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.description)}</div>
                <div class="row-meta">${escapeHtml(categoryLabel(item.category))} · ${formatDate(item.date)} · ${item.quantity} × ${money(item.unitCost)}</div></div>
                <strong>${money(item.quantity * item.unitCost)}</strong>
            </div>`).join('') || '<div class="empty-state">Nenhum lançamento nesta obra.</div>'}</div>

        <h3 class="detail-heading">Documentos <button class="table-action" data-action="new-document" data-preset-obra="${obra.id}">+ Arquivo</button></h3>
        <div class="data-list">${documents.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.name)}</div>
                <div class="row-meta">${escapeHtml(item.type ?? 'Geral')} · Adicionado em ${formatDate(item.date)}</div></div>
                ${item.path ? `<button class="table-action" data-download="${item.id}">Baixar</button>` : ''}
            </div>`).join('') || '<div class="empty-state">Nenhum documento nesta obra.</div>'}</div>

        <h3 class="detail-heading">Chamados</h3>
        <div class="data-list">${chamados.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                <div class="row-meta">${badge(item.priority)} · Aberto em ${item.date ? formatDateTime(item.date) : '—'}</div></div>
                ${badge(item.status)}
            </div>`).join('') || '<div class="empty-state">Nenhum chamado nesta obra.</div>'}</div>`;
}

export function render(ctx) {
    return layout('Obras', 'Portfólio de projetos e acompanhamento de execução.',
        ctx.auth.hasFullProjectAccess() ? '<button class="button button-primary" data-action="new-obra">+ Nova obra</button>' : '')
        + panel(ctx.auth.hasFullProjectAccess() ? 'Todos os projetos' : 'Obras designadas a você', `
            <div class="toolbar"><input class="field" id="obra-filter" placeholder="Buscar por nome ou cidade" aria-label="Buscar obras"></div>
            <div class="table-wrap"><table class="data-table">
                <thead><tr><th>Projeto</th><th>Status</th><th>Progresso</th><th>Orçamento</th><th>Responsáveis</th><th></th></tr></thead>
                <tbody id="obras-table"></tbody>
            </table></div>`);
}

export function bind(ctx) {
    const tbody = document.querySelector('#obras-table');
    const filter = document.querySelector('#obra-filter');
    const paint = term => {
        const query = term.toLowerCase();
        tbody.innerHTML = rows(ctx, ctx.store.obrasFor(ctx.auth.user())
            .filter(item => `${item.name} ${item.city}`.toLowerCase().includes(query)));
    };
    paint('');
    filter.addEventListener('input', event => paint(event.target.value));

    tbody.addEventListener('click', event => {
        // Detalhes: cronograma + financeiro + documentos + chamados da obra, num único painel.
        const viewId = event.target.closest('[data-view]')?.dataset.view;
        if (viewId) {
            const obra = ctx.store.state.obras.find(item => item.id === Number(viewId));
            if (obra) openInfoModal(escapeHtml(obra.name), detailHtml(ctx, obra));
            return;
        }

        // Designação de responsáveis (admin): checkboxes de usuários por obra.
        const obraId = event.target.closest('[data-assign]')?.dataset.assign;
        if (!obraId || !ctx.auth.isAdmin()) return;
        const obra = ctx.store.state.obras.find(item => item.id === Number(obraId));
        if (!obra) return;
        const atuais = new Set((obra.responsaveis ?? []).map(Number));
        openModal(`Responsáveis — ${obra.name}`, `
            <div class="check-list" id="assign-list">
                ${ctx.store.state.users.map(user => `
                    <label class="check-item">
                        <input type="checkbox" value="${user.id}" ${atuais.has(user.id) ? 'checked' : ''}>
                        <span><strong>${escapeHtml(user.name)}</strong> <small class="row-meta">${escapeHtml(user.email)}</small></span>
                    </label>`).join('') || '<div class="empty-state">Cadastre usuários primeiro.</div>'}
            </div>
            <div class="row-meta">Somente os responsáveis marcados (além do admin) verão esta obra ao entrar no painel.</div>`,
            async () => {
                const selecionados = [...document.querySelectorAll('#assign-list input:checked')]
                    .map(input => Number(input.value));
                await ctx.store.setResponsaveis(obra.id, selecionados);
            },
            () => { ctx.rerender(); notify('Responsáveis atualizados.'); });
    });
}
