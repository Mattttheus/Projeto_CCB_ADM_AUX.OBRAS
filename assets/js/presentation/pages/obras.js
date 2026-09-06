// Presentation/Pages — equivalente a page/gerenciar_obra.php
// Inclui a designação de responsáveis pelo admin (obra_responsaveis):
// cada responsável de igreja passa a ver apenas as obras designadas a ele.
import { obraStatusLabel } from '../../domain/obra/ObraStatus.js';
import { ROLE_LABELS } from '../../core/Auth.js';
import { layout, panel, badge, money, escapeHtml, openModal, notify } from '../ui.js';

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
                ${isAdmin ? `<button class="table-action" data-assign="${item.id}">Responsáveis</button>` : ''}
                ${isAdmin ? `<button class="table-action" data-delete="obra:${item.id}">Excluir</button>` : ''}
            </td>
        </tr>`;
    }).join('')
        : '<tr><td colspan="6"><div class="empty-state">Nenhuma obra encontrada.</div></td></tr>';
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

    // Designação de responsáveis (admin): checkboxes de usuários por obra.
    tbody.addEventListener('click', event => {
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
