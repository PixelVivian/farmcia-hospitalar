// assets/js/usuario.js
// Funcionalidades de Perfil (ver dados, trocar farmácia), Minhas Atividades
// (requisições já atendidas/canceladas pelo usuário logado) e, quando presente
// na página, Gestão de Equipe (listar/cadastrar usuários - farmacêutico/admin).
// Conecta com controllers/usuario_controller.php via fetch (AJAX).

document.addEventListener('DOMContentLoaded', function () {
    const formTrocarFarmacia = document.getElementById('form-trocar-farmacia');
    if (formTrocarFarmacia) {
        formTrocarFarmacia.addEventListener('submit', trocarFarmacia);
    }

    const formCadastrarUsuario = document.getElementById('form-cadastrar-usuario');
    if (formCadastrarUsuario) {
        formCadastrarUsuario.addEventListener('submit', cadastrarUsuario);
    }

    // Preenche o seletor de farmácias do Perfil assim que a página carrega
    // (não depende de o usuário já ter clicado na aba Perfil)
    carregarFarmaciasNoSeletorPerfil();
});

/**
 * Carrega a lista de farmácias e preenche o <select> de troca de farmácia do Perfil.
 */
async function carregarFarmaciasNoSeletorPerfil() {
    const select = document.getElementById('perfil-select-farmacia');
    if (!select) return;

    try {
        const resposta = await fetch('../controllers/usuario_controller.php?action=listar_farmacias');
        const farmacias = await resposta.json();
        select.innerHTML = farmacias
            .map(f => `<option value="${f.id_farmacia}">${f.sigla} - ${f.nome_completo}</option>`)
            .join('');
    } catch (erro) {
        console.error('Erro ao carregar farmácias:', erro);
    }
}

/**
 * Busca os dados do usuário logado e preenche a aba Perfil.
 */
async function carregarPerfil() {
    try {
        const resposta = await fetch('../controllers/usuario_controller.php?action=meu_perfil');
        const usuario = await resposta.json();

        document.getElementById('perfil-nome').innerText = usuario.nome;
        document.getElementById('perfil-email').innerText = usuario.email;
        document.getElementById('perfil-tipo').innerText = formatarPerfil(usuario.tipo_perfil);
        document.getElementById('perfil-farmacia').innerText = `${usuario.sigla_farmacia} - ${usuario.nome_farmacia}`;

        // Campos opcionais: só existem no HTML do dashboard farmacêutico
        const campoLogin = document.getElementById('perfil-login');
        if (campoLogin) campoLogin.innerText = usuario.login;

        const campoCrf = document.getElementById('perfil-crf');
        if (campoCrf) campoCrf.innerText = usuario.crf || 'Não informado';

        const select = document.getElementById('perfil-select-farmacia');
        if (select) select.value = usuario.id_farmacia;
    } catch (erro) {
        mostrarToast('Erro ao carregar perfil.', 'error');
    }
}

function formatarPerfil(tipo) {
    const nomes = { auxiliar: 'Auxiliar de Farmácia', farmaceutico: 'Farmacêutico(a)', admin: 'Administrador(a)' };
    return nomes[tipo] || tipo;
}

/**
 * Envia a troca de farmácia do usuário logado. Troca livre e imediata.
 */
async function trocarFarmacia(evento) {
    evento.preventDefault();
    const idFarmacia = document.getElementById('perfil-select-farmacia').value;

    try {
        const resposta = await fetch('../controllers/usuario_controller.php?action=trocar_farmacia', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_farmacia: idFarmacia }),
        });
        const dados = await resposta.json();

        if (dados.sucesso) {
            mostrarToast(`Farmácia atualizada para ${dados.sigla_farmacia}.`);
            carregarPerfil();
            // A fila geral depende da farmácia da sessão, então recarrega se estiver visível
            if (typeof carregarRequisicoes === 'function') carregarRequisicoes();
        } else {
            mostrarToast(dados.erro || 'Não foi possível trocar de farmácia.', 'error');
        }
    } catch (erro) {
        mostrarToast('Erro de conexão ao trocar farmácia.', 'error');
    }
}

/**
 * Carrega as requisições que o usuário logado já atendeu/cancelou (aba Minhas Atividades).
 */
async function carregarMinhasAtividades() {
    const tbody = document.getElementById('my-activities-list');
    const emptyState = document.getElementById('my-activities-empty');
    if (!tbody) return;

    try {
        const resposta = await fetch('../controllers/usuario_controller.php?action=minhas_atendidas');
        const requisicoes = await resposta.json();

        tbody.innerHTML = '';

        if (!requisicoes.length) {
            emptyState.classList.remove('hidden');
            return;
        }
        emptyState.classList.add('hidden');

        requisicoes.forEach(req => {
            const tr = document.createElement('tr');
            const sClass = req.status === 'Atendido' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-red-100 text-red-800 border-red-200';
            const itensResumo = (req.itens || []).map(i => `${i.quantidade_solicitada}x ${i.nome.split(' ')[0]}`).join(', ');

            tr.innerHTML = `
                <td class="px-6 py-4">
                    <span class="text-xs text-slate-400 block font-mono">#REQ-${req.id_requisicao}</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border ${sClass} mt-1 inline-block">${req.status}</span>
                </td>
                <td class="px-6 py-4 font-semibold text-slate-700">${req.nome_paciente || '-'}</td>
                <td class="px-6 py-4"><span class="bg-slate-100 px-2 py-1 rounded text-xs font-medium border border-slate-200">${req.setor_origem || req.sigla_origem}</span></td>
                <td class="px-6 py-4">
                    <span class="text-xs font-medium text-slate-700 block">${(req.itens || []).length} item(ns)</span>
                    <span class="text-[11px] text-slate-400 block max-w-xs truncate">${itensResumo}</span>
                </td>
                <td class="px-6 py-4 text-xs text-slate-500">${formatarDataBR(req.atendido_em)}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (erro) {
        mostrarToast('Erro ao carregar suas atividades.', 'error');
    }
}

/**
 * Carrega a lista de usuários cadastrados (aba Equipe - só farmacêutico/admin).
 */
async function carregarEquipe() {
    const tbody = document.getElementById('equipe-list');
    if (!tbody) return;

    try {
        const resposta = await fetch('../controllers/usuario_controller.php?action=listar_usuarios');
        const usuarios = await resposta.json();

        if (usuarios.erro) {
            mostrarToast(usuarios.erro, 'error');
            return;
        }

        tbody.innerHTML = '';
        usuarios.forEach(u => {
            const tr = document.createElement('tr');
            const perfilClass = u.tipo_perfil === 'admin' ? 'bg-purple-100 text-purple-800 border-purple-200'
                : u.tipo_perfil === 'farmaceutico' ? 'bg-teal-100 text-teal-800 border-teal-200'
                : 'bg-slate-100 text-slate-700 border-slate-200';

            tr.innerHTML = `
                <td class="px-6 py-4 font-semibold text-slate-700">${u.nome}</td>
                <td class="px-6 py-4 text-xs text-slate-500">${u.email}</td>
                <td class="px-6 py-4"><span class="px-2 py-0.5 rounded text-[10px] font-bold border ${perfilClass}">${formatarPerfil(u.tipo_perfil)}</span></td>
                <td class="px-6 py-4"><span class="bg-slate-100 px-2 py-1 rounded text-xs font-medium border border-slate-200">${u.sigla_farmacia}</span></td>
                <td class="px-6 py-4 text-xs">${u.ativo ? '<span class="text-emerald-600 font-semibold">Ativo</span>' : '<span class="text-slate-400">Inativo</span>'}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (erro) {
        mostrarToast('Erro ao carregar a equipe.', 'error');
    }
}

/**
 * Cadastra um novo usuário (auxiliar, farmacêutico ou admin) com senha padrão.
 */
async function cadastrarUsuario(evento) {
    evento.preventDefault();

    const corpo = {
        nome: document.getElementById('novo-usuario-nome').value.trim(),
        email: document.getElementById('novo-usuario-email').value.trim(),
        login: document.getElementById('novo-usuario-login').value.trim(),
        crf: document.getElementById('novo-usuario-crf').value.trim(),
        tipo_perfil: document.getElementById('novo-usuario-perfil').value,
        id_farmacia: document.getElementById('novo-usuario-farmacia').value,
    };

    try {
        const resposta = await fetch('../controllers/usuario_controller.php?action=cadastrar_usuario', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(corpo),
        });
        const dados = await resposta.json();

        if (dados.sucesso) {
            mostrarToast(`Usuário cadastrado! Senha padrão: ${dados.senha_padrao}`);
            document.getElementById('form-cadastrar-usuario').reset();
            carregarEquipe();
        } else {
            mostrarToast(dados.erro || 'Erro ao cadastrar usuário.', 'error');
        }
    } catch (erro) {
        mostrarToast('Erro de conexão ao cadastrar usuário.', 'error');
    }
}
