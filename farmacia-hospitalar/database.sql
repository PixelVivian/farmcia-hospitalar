-- =====================================================================
-- BANCO DE DADOS: Sistema de Farmácia Hospitalar
-- Gerenciamento de Requisições e Estoque entre Farmácias Internas
-- Versão 2: inclui paciente, prioridade e setor de origem na requisição
-- =====================================================================

CREATE DATABASE IF NOT EXISTS farmacia_hospitalar
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_general_ci;

USE farmacia_hospitalar;

-- ---------------------------------------------------------------------
-- TABELA 1: farmacias
-- Unidades fixas: FSAT, FINTV, FINTS, FINTG
-- Criada primeiro porque "usuarios" depende dela (chave estrangeira)
-- ---------------------------------------------------------------------
CREATE TABLE farmacias (
    id_farmacia     INT AUTO_INCREMENT PRIMARY KEY,
    sigla           VARCHAR(10) NOT NULL UNIQUE,
    nome_completo   VARCHAR(100) NOT NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABELA 2: usuarios
-- Diferencia quem é Auxiliar e quem é Farmacêutico através de tipo_perfil
-- O login pode ser feito por "login" (nome de usuário) ou por "email"
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario      INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(100) NOT NULL,
    login           VARCHAR(50) NOT NULL UNIQUE,
    email           VARCHAR(150) NOT NULL UNIQUE,
    senha           VARCHAR(255) NOT NULL,         -- armazenar sempre com password_hash()
    crf             VARCHAR(20) NULL,               -- registro profissional (só farmacêutico costuma ter)
    tipo_perfil     ENUM('auxiliar', 'farmaceutico', 'admin') NOT NULL,
    id_farmacia     INT NOT NULL,                   -- farmácia/unidade onde o usuário trabalha
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_farmacia
        FOREIGN KEY (id_farmacia) REFERENCES farmacias(id_farmacia)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABELA 3: medicamentos
-- Cadastro geral dos medicamentos (catálogo, sem quantidade aqui)
-- ---------------------------------------------------------------------
CREATE TABLE medicamentos (
    id_medicamento      INT AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(150) NOT NULL,
    principio_ativo     VARCHAR(150),
    unidade_medida      VARCHAR(20) NOT NULL,        -- ex: comprimido, ampola, frasco
    criado_em           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABELA 4: lotes
-- Controle de estoque real: vincula medicamento + farmácia + quantidade
-- Exclusivo de cadastro pelo Farmacêutico
-- ---------------------------------------------------------------------
CREATE TABLE lotes (
    id_lote             INT AUTO_INCREMENT PRIMARY KEY,
    id_medicamento      INT NOT NULL,
    id_farmacia         INT NOT NULL,
    numero_lote         VARCHAR(50) NOT NULL,
    quantidade          INT NOT NULL DEFAULT 0,
    data_validade       DATE NOT NULL,
    id_usuario_cadastro INT NOT NULL,                -- farmacêutico que cadastrou
    criado_em           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lote_medicamento
        FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento),
    CONSTRAINT fk_lote_farmacia
        FOREIGN KEY (id_farmacia) REFERENCES farmacias(id_farmacia),
    CONSTRAINT fk_lote_usuario
        FOREIGN KEY (id_usuario_cadastro) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABELA 5: requisicoes
-- Pedido entre farmácias: quem solicitou, farmácia destino, status
-- Inclui dados assistenciais (paciente, setor, prioridade) usados na tela
-- ---------------------------------------------------------------------
CREATE TABLE requisicoes (
    id_requisicao           INT AUTO_INCREMENT PRIMARY KEY,
    id_farmacia_origem      INT NOT NULL,            -- quem está pedindo
    id_farmacia_destino     INT NOT NULL,            -- quem vai atender
    id_usuario_solicitante  INT NOT NULL,
    id_usuario_atendente    INT NULL,                -- preenchido só quando for atendida
    nome_paciente           VARCHAR(150) NULL,        -- paciente associado à requisição (se houver)
    setor_origem            VARCHAR(100) NULL,        -- ex: UTI Adulto, Emergência, Bloco Cirúrgico
    prioridade              ENUM('Rotina', 'Alta', 'Urgente') NOT NULL DEFAULT 'Rotina',
    status                  ENUM('Pendente', 'Atendido', 'Cancelado') NOT NULL DEFAULT 'Pendente',
    observacao              TEXT,
    criado_em               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atendido_em             TIMESTAMP NULL,
    CONSTRAINT fk_req_farmacia_origem
        FOREIGN KEY (id_farmacia_origem) REFERENCES farmacias(id_farmacia),
    CONSTRAINT fk_req_farmacia_destino
        FOREIGN KEY (id_farmacia_destino) REFERENCES farmacias(id_farmacia),
    CONSTRAINT fk_req_usuario_solicitante
        FOREIGN KEY (id_usuario_solicitante) REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_req_usuario_atendente
        FOREIGN KEY (id_usuario_atendente) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABELA 6: itens_requisicao
-- Tabela associativa: uma requisição pode conter vários medicamentos
-- Guarda também o lote usado na dispensação (preenchido ao atender)
-- ---------------------------------------------------------------------
CREATE TABLE itens_requisicao (
    id_item                 INT AUTO_INCREMENT PRIMARY KEY,
    id_requisicao           INT NOT NULL,
    id_medicamento          INT NOT NULL,
    quantidade_solicitada   INT NOT NULL,
    quantidade_atendida     INT NULL,
    numero_lote_dispensado  VARCHAR(50) NULL,         -- lote informado no momento da dispensação
    CONSTRAINT fk_item_requisicao
        FOREIGN KEY (id_requisicao) REFERENCES requisicoes(id_requisicao)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_medicamento
        FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento)
) ENGINE=InnoDB;

-- =====================================================================
-- DADOS INICIAIS (SEED)
-- =====================================================================

-- 4 unidades fixas exigidas pelo sistema
INSERT INTO farmacias (sigla, nome_completo) VALUES
('FSAT',  'Farmácia Satélite'),
('FINTV', 'Farmácia Interna V'),
('FINTS', 'Farmácia Interna S'),
('FINTG', 'Farmácia Interna G');

-- Usuários reais cadastrados
-- Senha de ambos (texto puro, só para teste): 123456
-- Hashes gerados com bcrypt, compatíveis com password_verify() do PHP.
INSERT INTO usuarios (nome, login, email, senha, crf, tipo_perfil, id_farmacia) VALUES
('Viviane', 'viviane', 'vivianvivsaude@gmail.com', '$2b$10$bWQbNDe5RA8HxgEzp2mpNud1B/KZhidBFRmTIkGanou80Q3p3yHz2', NULL, 'farmaceutico', 1),
('Isalai',  'isalai',  'isalaivivsaude@gmail.com', '$2b$10$r5.bBcyBzgQUds9qtRtxK.M89jp5hCaOBkxWRwTnMpAzfWcs863K6', NULL, 'auxiliar', 2);

-- Medicamentos de exemplo (catálogo)
INSERT INTO medicamentos (nome, principio_ativo, unidade_medida) VALUES
('Dipirona Monoidratada 500mg/mL', 'Dipirona', 'ampola'),
('Cloridrato de Midazolam 5mg/mL', 'Midazolam', 'ampola'),
('Soro Fisiológico 0,9% 500mL', 'Cloreto de Sódio', 'bolsa'),
('Soro Glicosado 5% 250mL', 'Glicose', 'bolsa'),
('Ceftriaxona Sódica 1g', 'Ceftriaxona', 'frasco'),
('Omeprazol 40mg Injetável', 'Omeprazol', 'frasco'),
('Ondansetrona 2mg/mL', 'Ondansetrona', 'ampola'),
('Sulfato de Morfina 10mg/mL', 'Morfina', 'ampola');

-- Requisições de exemplo (algumas pendentes, uma já atendida)
-- id_usuario 1 = Viviane (farmacêutica) | id_usuario 2 = Isalai (auxiliar)
INSERT INTO requisicoes
    (id_farmacia_origem, id_farmacia_destino, id_usuario_solicitante, id_usuario_atendente,
     nome_paciente, setor_origem, prioridade, status, criado_em, atendido_em)
VALUES
    (2, 1, 2, NULL, 'Ana Paula de Oliveira', 'UTI Adulto', 'Urgente', 'Pendente', NOW(), NULL),
    (3, 1, 2, NULL, 'Carlos Eduardo Santos', 'Emergência', 'Alta', 'Pendente', NOW(), NULL),
    (4, 1, 2, 1, 'Maria Clara Machado', 'Clínica Médica', 'Rotina', 'Atendido', NOW(), NOW());

-- Itens das requisições de exemplo
INSERT INTO itens_requisicao (id_requisicao, id_medicamento, quantidade_solicitada, quantidade_atendida, numero_lote_dispensado) VALUES
(1, 1, 2, NULL, NULL),
(1, 3, 1, NULL, NULL),
(2, 5, 1, NULL, NULL),
(3, 7, 2, 2, 'L-VIV904');
