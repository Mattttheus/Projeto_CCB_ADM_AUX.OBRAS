-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 28-Ago-2026 às 20:36
-- Versão do servidor: 8.0.31
-- versão do PHP: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `auxiliar_obras`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `atividades`
--

DROP TABLE IF EXISTS `atividades`;
CREATE TABLE IF NOT EXISTS `atividades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `data_atividade` date DEFAULT NULL,
  `tipo` enum('unico','recorrente') COLLATE utf8mb4_unicode_ci DEFAULT 'unico',
  `dia_semana` int DEFAULT NULL,
  `obra_id` int DEFAULT NULL,
  `data_limite` date DEFAULT NULL,
  `status` enum('pendente','em_andamento','concluida') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `obra_id` (`obra_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `atividades`
--

INSERT INTO `atividades` (`id`, `titulo`, `descricao`, `data_atividade`, `tipo`, `dia_semana`, `obra_id`, `data_limite`, `status`, `created_at`) VALUES
(1, 'lajes', '', '2026-08-07', 'unico', NULL, 1, '2026-08-07', 'concluida', '2026-08-10 01:01:04');

-- --------------------------------------------------------

--
-- Estrutura da tabela `chamados`
--

DROP TABLE IF EXISTS `chamados`;
CREATE TABLE IF NOT EXISTS `chamados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `obra_id` int DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioridade` enum('verde','amarelo','vermelho') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verde',
  `status` enum('aberto','em_atendimento','resolvido','fechado') COLLATE utf8mb4_unicode_ci DEFAULT 'aberto',
  `data_abertura` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fechamento` datetime DEFAULT NULL,
  `fechado_por` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `obra_id` (`obra_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `chamados`
--

INSERT INTO `chamados` (`id`, `obra_id`, `usuario_id`, `titulo`, `descricao`, `prioridade`, `status`, `data_abertura`, `data_fechamento`, `fechado_por`) VALUES
(1, 1, 2, 'rgqrg', 'rgqregqer', 'vermelho', 'fechado', '2026-08-10 01:24:27', '2026-08-09 23:02:21', 2),
(2, 1, 2, 'vaf', 'avgwsfvasf', 'amarelo', 'fechado', '2026-08-10 01:46:48', '2026-08-09 23:02:18', 2),
(3, 1, NULL, 'H4H454', 'H45EH4', 'verde', 'fechado', '2026-08-10 02:45:33', '2026-08-09 23:52:35', 2),
(4, 1, 2, 'SDGHSDT', 'SGJSGFDJ', 'amarelo', 'fechado', '2026-08-10 03:27:46', '2026-08-10 00:32:12', 2),
(5, 1, 2, 'yuly', 'iltuil', 'vermelho', 'fechado', '2026-08-10 03:36:25', '2026-08-10 00:43:03', 2),
(6, 1, 2, 'falta arroz', 'sbstrwsrt', 'vermelho', 'fechado', '2026-08-10 23:55:03', '2026-08-10 20:55:17', 2);

-- --------------------------------------------------------

--
-- Estrutura da tabela `compras`
--

DROP TABLE IF EXISTS `compras`;
CREATE TABLE IF NOT EXISTS `compras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `obra_id` int NOT NULL,
  `tipo` enum('produto','material') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` int NOT NULL DEFAULT '1',
  `valor` decimal(10,2) NOT NULL DEFAULT '0.00',
  `data_compra` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `obra_id` (`obra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `documentos_obras`
--

DROP TABLE IF EXISTS `documentos_obras`;
CREATE TABLE IF NOT EXISTS `documentos_obras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `obra_id` int NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Geral',
  `data_upload` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `obra_id` (`obra_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `documentos_obras`
--

INSERT INTO `documentos_obras` (`id`, `obra_id`, `nome_arquivo`, `caminho_arquivo`, `tipo_documento`, `data_upload`) VALUES
(1, 1, 'planta', 'uploads/obras/1/1766105082026_1786332954.pdf', 'Planta', '2026-08-10 03:35:54');

-- --------------------------------------------------------

--
-- Estrutura da tabela `lancamentos_financeiros`
--

DROP TABLE IF EXISTS `lancamentos_financeiros`;
CREATE TABLE IF NOT EXISTS `lancamentos_financeiros` (
  `id` int NOT NULL AUTO_INCREMENT,
  `obra_id` int NOT NULL,
  `categoria` enum('material','operacional','servico','equipamento','produto') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` decimal(12,2) NOT NULL DEFAULT '1.00',
  `valor_unitario` decimal(14,2) NOT NULL DEFAULT '0.00',
  `data_lancamento` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_financeiro_obra_data` (`obra_id`,`data_lancamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `obras`
--

DROP TABLE IF EXISTS `obras`;
CREATE TABLE IF NOT EXISTS `obras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int DEFAULT '1',
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('em_andamento','concluida','pausada') COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `obras`
--

INSERT INTO `obras` (`id`, `empresa_id`, `nome`, `status`, `created_at`) VALUES
(1, 1, 'Jardim Vitória', 'em_andamento', '2026-08-10 00:44:14'),
(2, 1, 'MARCELINO', 'em_andamento', '2026-08-10 02:51:24');

-- --------------------------------------------------------

--
-- Estrutura da tabela `obra_responsaveis`
--

DROP TABLE IF EXISTS `obra_responsaveis`;
CREATE TABLE IF NOT EXISTS `obra_responsaveis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `obra_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `obra_id` (`obra_id`),
  KEY `usuario_id` (`usuario_id`),
  UNIQUE KEY `uq_obra_responsavel` (`obra_id`,`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `orcamentos_obras`
--

DROP TABLE IF EXISTS `orcamentos_obras`;
CREATE TABLE IF NOT EXISTS `orcamentos_obras` (
  `obra_id` int NOT NULL,
  `valor_orcado` decimal(14,2) NOT NULL DEFAULT '0.00',
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`obra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int DEFAULT '1',
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'comum',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `empresa_id`, `nome`, `email`, `senha`, `role`, `tipo`, `created_at`) VALUES
(4, 1, 'david', 'theus.vini4655@outlook.com.br', '$2y$10$uk8CAReBQg5eS8S.3tH6UusIvTcKyW7oxz/YHGBy3qitUdz5teTmm', 'admin', 'admin', '2026-08-11 22:52:33'),
(5, 1, 'matheus', 'matheus.suporte@auxiliarobras.com.br', '$2y$10$V1/OoERZIMBPELf.8cmZ4eqKD/73fGPOpS0REWu8rb5SD8UJfHokK', 'suporte', 'suporte', '2026-08-11 23:02:23'),
(6, 1, 'matheus', 'teste@teste.com', '$2y$10$NJy6/SXJkuI5ZICdChm7.u7uIxNpOEjHEVLrI6wngiueboJg4lsm.', 'engenheiro', 'engenheiro', '2026-08-11 23:05:05');

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `atividades`
--
ALTER TABLE `atividades`
  ADD CONSTRAINT `fk_atividades_obras` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `chamados`
--
ALTER TABLE `chamados`
  ADD CONSTRAINT `fk_chamados_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compras_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `documentos_obras`
--
ALTER TABLE `documentos_obras`
  ADD CONSTRAINT `fk_documentos_obras` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `lancamentos_financeiros`
--
ALTER TABLE `lancamentos_financeiros`
  ADD CONSTRAINT `fk_lancamentos_financeiros_obras` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `obra_responsaveis`
--
ALTER TABLE `obra_responsaveis`
  ADD CONSTRAINT `fk_resp_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_resp_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `orcamentos_obras`
--
ALTER TABLE `orcamentos_obras`
  ADD CONSTRAINT `fk_orcamentos_obras` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
