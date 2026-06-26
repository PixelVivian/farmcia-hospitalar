-- =====================================================================
-- ATUALIZAÇÃO INCREMENTAL: adiciona coluna email e cadastra 2 usuários
-- Use este script no banco que JÁ EXISTE, sem apagar nada.
-- =====================================================================

USE farmacia_hospitalar;

-- 1) Adiciona a coluna "email" na tabela usuarios (ainda não existe nela)
ALTER TABLE usuarios
    ADD COLUMN email VARCHAR(150) NOT NULL UNIQUE AFTER login;

-- 2) Cadastra os dois usuários novos (Farmacêutico e Auxiliar)
-- Senha de ambos (texto puro, só para teste): 123456
-- id_farmacia: 1 = FSAT | 2 = FINTV | 3 = FINTS | 4 = FINTG
-- Ajuste o id_farmacia abaixo se quiser outra unidade para cada um.
INSERT INTO usuarios (nome, login, email, senha, crf, tipo_perfil, id_farmacia) VALUES
('Viviane', 'viviane', 'vivianvivsaude@gmail.com', '$2b$10$bWQbNDe5RA8HxgEzp2mpNud1B/KZhidBFRmTIkGanou80Q3p3yHz2', NULL, 'farmaceutico', 1),
('Isalai',  'isalai',  'isalaivivsaude@gmail.com', '$2b$10$r5.bBcyBzgQUds9qtRtxK.M89jp5hCaOBkxWRwTnMpAzfWcs863K6', NULL, 'auxiliar', 2);
