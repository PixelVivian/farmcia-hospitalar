// assets/js/validacao.js
// Validação básica no front-end, só para melhorar a experiência do usuário.
// A validação que realmente importa continua sendo feita no PHP
// (controllers/login_controller.php), nunca confie só no JS.

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-login');
    const campoLogin = document.getElementById('login');
    const campoSenha = document.getElementById('senha');
    const btnEntrar = document.getElementById('btn-entrar');

    if (!form) return;

    form.addEventListener('submit', function (evento) {
        let valido = true;

        // Limpa mensagens de erro anteriores
        document.querySelectorAll('.erro-campo').forEach(function (el) {
            el.style.display = 'none';
        });

        if (campoLogin.value.trim() === '') {
            mostrarErroCampo(campoLogin, 'Informe o usuário.');
            valido = false;
        }

        if (campoSenha.value.trim() === '') {
            mostrarErroCampo(campoSenha, 'Informe a senha.');
            valido = false;
        }

        if (!valido) {
            evento.preventDefault();
            return;
        }

        // Evita duplo clique enquanto o formulário processa o login
        btnEntrar.disabled = true;
        btnEntrar.textContent = 'Entrando...';
    });

    function mostrarErroCampo(campo, mensagem) {
        const erroEl = campo.parentElement.querySelector('.erro-campo');
        if (erroEl) {
            erroEl.textContent = mensagem;
            erroEl.style.display = 'block';
        }
    }
});
