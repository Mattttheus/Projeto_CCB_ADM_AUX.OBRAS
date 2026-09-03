// Presentation/Pages — controle de usuários (restrito a admin, como Auth::requireAdmin no PHP)
import { ROLE_LABELS } from '../../core/Auth.js';
import { layout, panel, badge, escapeHtml, openModal, notify } from '../ui.js';
import { Validator } from '../../core/Validator.js';

export function renderUsuarios(ctx) {
    const users = ctx.store.state.users;
    return layout('Usuários', 'Pessoas com acesso ao espaço de trabalho (somente administradores).',
        '<button class="button button-primary" data-action="invite-user">+ Cadastrar usuário</button>')
        + panel('Equipe cadastrada', `
            <div class="table-wrap"><table class="data-table">
                <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Acesso</th><th></th></tr></thead>
                <tbody>${users.map(item => `
                    <tr>
                        <td><strong>${escapeHtml(item.name)}</strong></td>
                        <td>${escapeHtml(item.email)}</td>
                        <td>${badge(item.role, ROLE_LABELS[item.role] ?? item.role)}</td>
                        <td>${badge(item.active ? 'liberado' : 'bloqueado')}</td>
                        <td>${item.id !== ctx.auth.user()?.id
                ? `<button class="table-action" data-toggle-user="${item.id}">${item.active ? 'Bloquear' : 'Liberar'}</button>`
                : '<span class="row-meta">Você</span>'}</td>
                    </tr>`).join('')}</tbody>
            </table></div>`);
}

export function bindUsuarios(ctx) {
    document.querySelector('[data-action="invite-user"]').addEventListener('click', () => {
        openModal('Cadastrar usuário', `
            <div class="form-grid">
                <div class="form-field full"><label for="f-name">Nome</label><input class="field" id="f-name" name="nome" required></div>
                <div class="form-field full"><label for="f-email">E-mail</label><input class="field" id="f-email" name="email" type="email" required></div>
                <div class="form-field"><label for="f-role">Perfil</label>
                    <select class="field" id="f-role" name="perfil"><option value="colaborador">Colaborador</option><option value="suporte">Suporte</option><option value="admin">Administrador</option></select></div>
                <div class="form-field"><label for="f-password">Senha</label><input class="field" id="f-password" name="senha" required minlength="6"></div>
            </div>`,
            data => {
                const name = Validator.requiredText(data.nome, 'o nome');
                const email = Validator.email(data.email);
                const password = Validator.requiredText(data.senha, 'a senha', 72);
                const role = Validator.oneOf(data.perfil, Object.keys(ROLE_LABELS), 'Perfil');
                if (ctx.store.state.users.some(item => item.email.toLowerCase() === email.toLowerCase())) {
                    throw new Error('E-mail já cadastrado.');
                }
                ctx.store.state.users.push({
                    id: ctx.store.nextId(ctx.store.state.users),
                    name, email, password, role, active: false,
                });
                ctx.store.log(`Usuário ${name} cadastrado (aguardando liberação).`);
                ctx.store.save();
            },
            () => { ctx.rerender(); notify('Usuário cadastrado aguardando liberação.'); });
    });
}

export function renderLogs(ctx) {
    const logs = ctx.store.state.logs;
    return layout('Logs de e-mail', 'Histórico local das notificações e eventos do sistema.')
        + panel('Atividade recente', `<div class="data-list">${logs.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.message)}</div>
                <div class="row-meta">${new Date(item.date).toLocaleString('pt-BR')}</div></div>
                ${badge('informativo')}
            </div>`).join('') || '<div class="empty-state">Nenhum evento registrado.</div>'}</div>`);
}
