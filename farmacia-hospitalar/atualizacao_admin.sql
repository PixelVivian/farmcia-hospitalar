-- =====================================================================
-- ATUALIZAÇÃO INCREMENTAL 2: adiciona perfil "admin"
-- Use este script no banco que JÁ EXISTE, sem apagar nada.
-- =====================================================================

USE farmacia_hospitalar;

-- 1) Adiciona 'admin' como opção válida no ENUM de tipo_perfil.
--    MySQL exige reescrever o ENUM completo (não dá para "adicionar" direto).
ALTER TABLE usuarios
    MODIFY COLUMN tipo_perfil ENUM('auxiliar', 'farmaceutico', 'admin') NOT NULL;

-- 2) (Opcional) Se quiser já promover alguém para admin agora, descomente
--    e ajuste o e-mail abaixo. Por padrão, ninguém é admin ainda.
-- UPDATE usuarios SET tipo_perfil = 'admin' WHERE email = 'seuemail@exemplo.com';
