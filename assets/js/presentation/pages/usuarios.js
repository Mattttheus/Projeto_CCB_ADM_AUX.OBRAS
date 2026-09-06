// Presentation/Pages — dashboard de controle de acessos (restrito a admin).
// Aqui o administrador define o NÍVEL de cada usuário e designa as OBRAS de cada
// responsável: o responsável de igreja (Colaborador) acessa apenas suas obras.
// No modo Supabase, o cadastro cria a conta no Supabase Auth e o perfil em `usuarios`.
import { ROLE_LABELS, FULL_ACCESS_ROLES } from '../../core/Auth.js';
import { BACKEND_MODE } from '../../infrastructure/persistence/Store.js';
import { layout, panel, stat, badge, escapeHtml, openModal, notify } from '../ui.js';
import { Validator } from '../../core/Validator.js';

const ROLE_KEYS = ['colaborador', 'mestre_obras', 'engenheiro', 'suporte', 'admin'];

function obrasDoUsuario(ctx, user) {
    return ctx.store.state.obras.filter(obra => obra.responsaveis?.includes(user.id));
}

function rows(ctx) {
    const meId = ctx.auth.user()?.id;
    return ctx.store.state.users.map(item => {
        const designadas = obrasDoUsuario(ctx, item);
        const fullAccess = FULL_ACCESS_ROLES.includes(item.role);
        const own = item.id === meId;
        return `
        <tr>
            <td><strong>${escapeHtml(item.name)}</strong>${own ? ' <span class="row-meta">(você)</span>' : ''}</td>
            <td>${escapeHtml(item.email)}</td>
            <td>${badge(item.role, ROLE_LABELS[item.role] ?? item.role)}</td>
            <td>${fullAccess
                ? '<span class="row-meta">Acesso total a todas as obras</span>'
                : designadas.length
                    ? designadas.map(obra => `<span class="chip">${escapeHtml(obra.name)}</span>`).join('')
                    : '<span class="row-meta">Nenhuma obra designada</span>'}</td>
            <td>${badge(item.active ? 'liberado' : 'bloqueado')}</td>
            <td class="table-actions">${own
                ? '<span class="row-meta">Sua conta</span>'
                : `<button class="table-action" data-edit-role="${item.id}">Nível</button>
                   <button class="table-action" data-assign-obras="${item.id}">Obras</button>
                   <button class="table-action" data-toggle-user="${item.id}">${item.active ? 'Bloquear' : 'Liberar'}</button>`}</td>
        </tr>`;
    }).join('');
}

export function renderUsuarios(ctx) {
    const users = ctx.store.state.users;
    const responsaveis = users.filter(item => !FULL_ACCESS_ROLES.includes(item.role));
    const semObra = responsaveis.filter(item => obrasDoUsuario(ctx, item).length === 0).length;
    return layout('Usuários', 'Dashboard de controle de acessos — defina o nível de cada usuário e as obras de cada responsável.',
        '<button class="button button-primary" data-action="invite-user">+ Cadastrar usuário</button>')
        + `<div class="stats-grid">
            ${stat('Usuários', users.length, 'Contas cadastradas', 'US')}
            ${stat('Acesso total', users.filter(item => FULL_ACCESS_ROLES.includes(item.role)).length, 'Admin, suporte, engenharia e mestre', 'AD')}
            ${stat('Responsáveis', responsaveis.length, 'Vêm apenas as obras designadas', 'RE')}
            ${stat('Sem obra', semObra, 'Responsáveis aguardando designação', '!', semObra ? 'negative' : 'positive')}
        </div>`
        + panel('Equipe cadastrada', `
            <div class="table-wrap"><table class="data-table" id="users-table">
                <thead><tr><th>Nome</th><th>E-mail</th><th>Nível</th><th>Obras designadas</th><th>Acesso</th><th></th></tr></thead>
                <tbody>${rows(ctx)}</tbody>
            </table></div>
            <div class="row-meta">Responsáveis (nível Colaborador) acessam somente as obras designadas aqui ou em <b>Obras → Responsáveis</b>. Os demais níveis têm acesso total.</div>`);
}

export function bindUsuarios(ctx) {
    document.querySelector('[data-action="invite-user"]').addEventListener('click', () => {
        openModal('Cadastrar usuário', `
            <div class="form-grid">
                <div class="form-field full"><label for="f-name">Nome</label><input class="field" id="f-name" name="nome" required></div>
                <div class="form-field full"><label for="f-email">E-mail</label><input class="field" id="f-email" name="email" type="email" required></div>
                <div class="form-field"><label for="f-role">Perfil</label>
                    <select class="field" id="f-role" name="perfil">
                        <option value="colaborador">Colaborador (responsável de igreja)</option>
                        <option value="mestre_obras">Mestre de obras</option>
                        <option value="engenheiro">Engenheiro</option>
                        <option value="suporte">Suporte</option>
                        <option value="admin">Administrador</option>
                    </select></div>
                <div class="form-field"><label for="f-password">Senha</label><input class="field" id="f-password" name="senha" required minlength="6"></div>
            </div>`,
            async data => {
                await ctx.store.createUser({
                    name: Validator.requiredText(data.nome, 'o nome'),
                    email: Validator.email(data.email),
                    password: Validator.requiredText(data.senha, 'a senha', 72),
                    role: Validator.oneOf(data.perfil, ['admin', 'suporte', 'engenheiro', 'mestre_obras', 'colaborador'], 'Perfil'),
                });
            },
            () => {
                ctx.rerender();
                notify(BACKEND_MODE === 'supabase'
                    ? 'Usuário cadastrado e liberado. Agora defina as obras dele no botão "Obras".'
                    : 'Usuário cadastrado aguardando liberação.');
            });
    });

    // Ações da tabela: alterar nível de acesso e designar obras do responsável.
    document.querySelector('#users-table').addEventListener('click', event => {
        const roleTarget = event.target.closest('[data-edit-role]')?.dataset.editRole;
        if (roleTarget) {
            const user = ctx.store.state.users.find(item => item.id === Number(roleTarget));
            if (!user) return;
            openModal(`Nível de acesso — ${user.name}`, `
                <div class="form-grid">
                    <div class="form-field full"><label for="f-urole">Perfil</label>
                        <select class="field" id="f-urole" name="perfil">${ROLE_KEYS.map(key =>
                `<option value="${key}" ${user.role === key ? 'selected' : ''}>${ROLE_LABELS[key]}</option>`).join('')}</select></div>
                </div>
                <div class="row-meta">Somente o nível Colaborador fica limitado às obras designadas; os demais níveis têm acesso total.</div>`,
                async data => {
                    await ctx.store.setUserRole(user.id, Validator.oneOf(data.perfil, ROLE_KEYS, 'Perfil'));
                },
                () => { ctx.rerender(); notify('Nível de acesso atualizado.'); });
        }

        const assignTarget = event.target.closest('[data-assign-obras]')?.dataset.assignObras;
        if (assignTarget) {
            const user = ctx.store.state.users.find(item => item.id === Number(assignTarget));
            if (!user) return;
            const atuais = new Set(obrasDoUsuario(ctx, user).map(obra => obra.id));
            openModal(`Obras designadas — ${user.name}`, `
                <div class="check-list" id="assign-obras-list">
                    ${ctx.store.state.obras.map(obra => `
                        <label class="check-item">
                            <input type="checkbox" value="${obra.id}" ${atuais.has(obra.id) ? 'checked' : ''}>
                            <span><strong>${escapeHtml(obra.name)}</strong> <small class="row-meta">${escapeHtml(obra.city ?? '')}</small></span>
                        </label>`).join('') || '<div class="empty-state">Cadastre obras primeiro.</div>'}
                </div>
                <div class="row-meta">Este usuário verá apenas as obras marcadas no painel, no calendário, no financeiro e nos documentos.</div>`,
                async () => {
                    const selecionadas = [...document.querySelectorAll('#assign-obras-list input:checked')]
                        .map(input => Number(input.value));
                    await ctx.store.setObrasForUser(user.id, selecionadas);
                },
                () => { ctx.rerender(); notify('Obras designadas atualizadas.'); });
        }
    });
}

export function renderLogs(ctx) {
    const logs = ctx.store.state.logs;
    return layout('Logs de e-mail', 'Histórico das notificações e eventos do sistema.')
        + panel('Atividade recente', `<div class="data-list">${logs.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.message)}</div>
                <div class="row-meta">${new Date(item.date).toLocaleString('pt-BR')}</div></div>
                ${badge('informativo')}
            </div>`).join('') || '<div class="empty-state">Nenhum evento registrado.</div>'}</div>`);
}
