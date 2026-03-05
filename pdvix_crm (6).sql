-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 05/03/2026 às 07:57
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `pdvix_crm`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixa_sangrias`
--

CREATE TABLE `caixa_sangrias` (
  `id` int(11) NOT NULL,
  `caixa_sessao_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `data_hora` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Sangrias (retiradas) de caixa vindas do PDV offline';

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixa_sessoes`
--

CREATE TABLE `caixa_sessoes` (
  `id` int(11) NOT NULL,
  `numero_pdv` varchar(10) NOT NULL DEFAULT '01' COMMENT 'Identificador do PDV (01, 02...)',
  `usuario_id` int(11) NOT NULL,
  `abertura_em` datetime NOT NULL,
  `fechamento_em` datetime DEFAULT NULL,
  `valor_abertura` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_dinheiro` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_pix` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_debito` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_credito` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_convenio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_outros` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_vendas` int(11) NOT NULL DEFAULT 0,
  `total_canceladas` int(11) NOT NULL DEFAULT 0,
  `total_sangrias` decimal(10,2) NOT NULL DEFAULT 0.00,
  `saldo_esperado` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'abertura + dinheiro - sangrias',
  `caixa_contado` decimal(10,2) DEFAULT NULL COMMENT 'Valor físico contado pelo operador',
  `diferenca` decimal(10,2) DEFAULT NULL COMMENT 'contado - saldo_esperado',
  `status` enum('aberto','fechado') NOT NULL DEFAULT 'aberto',
  `observacao` text DEFAULT NULL,
  `sincronizado_em` datetime DEFAULT NULL COMMENT 'Data/hora da sincronização do PDV',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Sessões de caixa sincronizadas dos PDVs offline';

--
-- Despejando dados para a tabela `caixa_sessoes`
--

INSERT INTO `caixa_sessoes` (`id`, `numero_pdv`, `usuario_id`, `abertura_em`, `fechamento_em`, `valor_abertura`, `total_dinheiro`, `total_pix`, `total_debito`, `total_credito`, `total_convenio`, `total_outros`, `total_vendas`, `total_canceladas`, `total_sangrias`, `saldo_esperado`, `caixa_contado`, `diferenca`, `status`, `observacao`, `sincronizado_em`, `created_at`, `updated_at`) VALUES
(1, '01', 1, '2026-03-05 06:17:46', '2026-03-05 06:18:14', 0.00, 78.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, 0, 0.00, 78.00, NULL, NULL, 'fechado', NULL, '2026-03-05 02:18:14', '2026-03-05 02:18:14', '2026-03-05 02:18:14'),
(2, '01', 1, '2026-03-05 06:54:57', '2026-03-05 06:55:55', 100.00, 936.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, 0, 0.00, 1036.00, NULL, NULL, 'fechado', NULL, '2026-03-05 02:55:55', '2026-03-05 02:55:55', '2026-03-05 02:55:55');

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(160) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `config`
--

CREATE TABLE `config` (
  `chave` varchar(80) NOT NULL,
  `valor` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `config`
--

INSERT INTO `config` (`chave`, `valor`) VALUES
('api_token', '7f6b4d0f-1823-11f1-ada3-988389d8ef6c'),
('versao', '2.0.0');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque`
--

CREATE TABLE `estoque` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade_atual` int(11) NOT NULL DEFAULT 0,
  `data_atualizacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `estoque`
--

INSERT INTO `estoque` (`id`, `produto_id`, `quantidade_atual`, `data_atualizacao`) VALUES
(4, 1, 0, '2026-03-05 02:55:31'),
(5, 2, 700, '2026-03-04 09:27:50'),
(6, 3, 0, '2026-03-05 01:26:54');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque_movimentacoes`
--

CREATE TABLE `estoque_movimentacoes` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `tipo_movimento` enum('ENTRADA','SAIDA','AJUSTE') NOT NULL,
  `quantidade` decimal(10,3) NOT NULL,
  `unidade_origem` enum('UN','CX','KG','G') NOT NULL,
  `codigo_barras_usado` varchar(50) NOT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `data_movimento` datetime DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `origem` enum('VENDA','COMPRA','AJUSTE','DEVOLUCAO','ESTORNO') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `estoque_movimentacoes`
--

INSERT INTO `estoque_movimentacoes` (`id`, `produto_id`, `tipo_movimento`, `quantidade`, `unidade_origem`, `codigo_barras_usado`, `motivo`, `referencia_id`, `data_movimento`, `usuario_id`, `origem`) VALUES
(4, 1, 'ENTRADA', 1.000, 'CX', '78999999999', '', NULL, '2026-03-04 07:52:19', 1, 'COMPRA'),
(5, 1, 'SAIDA', 1.000, 'UN', '78999999999', '', NULL, '2026-03-04 07:53:01', 1, 'VENDA'),
(6, 2, 'ENTRADA', 1.000, 'KG', '', '', NULL, '2026-03-04 09:26:54', 1, 'COMPRA'),
(7, 2, 'SAIDA', 0.300, 'G', '', '', NULL, '2026-03-04 09:27:13', 1, 'VENDA'),
(8, 2, 'SAIDA', 300.000, 'G', '', '', NULL, '2026-03-04 09:27:50', 1, 'VENDA'),
(9, 1, 'SAIDA', 3.000, 'UN', '123', 'Sync PDV offline — VND1772687044360', 8, '2026-03-05 01:04:07', 1, 'VENDA'),
(10, 3, 'SAIDA', 1.000, 'UN', '2301', 'Sync PDV offline — VND1772687105426', 9, '2026-03-05 01:05:06', 1, 'VENDA'),
(11, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — VND1772687135516', 10, '2026-03-05 01:05:44', 1, 'VENDA'),
(12, 1, 'SAIDA', 20.000, 'UN', '123', 'Sync PDV offline — VND1772687226878', 11, '2026-03-05 01:07:09', 1, 'VENDA'),
(13, 3, 'ENTRADA', 2.000, 'CX', '', '', NULL, '2026-03-05 01:08:28', 1, 'COMPRA'),
(14, 3, 'SAIDA', 12.000, 'UN', '2301', 'Sync PDV offline — VND1772687349016', 12, '2026-03-05 01:09:10', 1, 'VENDA'),
(15, 3, 'SAIDA', 1.000, 'UN', '2301', 'Sync PDV offline — VND1772687437612', 13, '2026-03-05 01:10:39', 1, 'VENDA'),
(16, 1, 'SAIDA', 24.000, 'UN', '123', 'Sync PDV offline — VND1772688407546', 14, '2026-03-05 01:26:54', 1, 'VENDA'),
(17, 3, 'SAIDA', 12.000, 'UN', '2301', 'Sync PDV offline — VND1772688407546', 14, '2026-03-05 01:26:54', 1, 'VENDA'),
(18, 1, 'SAIDA', 12.000, 'UN', '123', 'Sync PDV offline — VND1772688518476', 15, '2026-03-05 01:28:44', 1, 'VENDA'),
(19, 1, 'SAIDA', 12.000, 'UN', '123', 'Sync PDV offline — VND1772689002498', 16, '2026-03-05 01:36:43', 1, 'VENDA'),
(20, 1, 'SAIDA', 13.000, 'UN', '123', 'Sync PDV offline — VND1772691313659', 17, '2026-03-05 02:15:24', 1, 'VENDA'),
(21, 1, 'SAIDA', 12.000, 'UN', '123', 'Sync PDV offline — VND1772691340624', 18, '2026-03-05 02:15:47', 1, 'VENDA'),
(22, 1, 'SAIDA', 13.000, 'UN', '123', 'Sync PDV offline — VND1772691474343', 19, '2026-03-05 02:17:56', 1, 'VENDA'),
(23, 1, 'SAIDA', 12.000, 'UN', '123', 'Sync PDV offline — VND1772693709106', 20, '2026-03-05 02:55:31', 1, 'VENDA');

-- --------------------------------------------------------

--
-- Estrutura para tabela `fornecedores`
--

CREATE TABLE `fornecedores` (
  `id` int(11) NOT NULL,
  `cnpj` varchar(160) NOT NULL,
  `razao_social` varchar(160) NOT NULL,
  `nome_fantasia` varchar(160) DEFAULT NULL,
  `endereco` text NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `ativo` int(11) NOT NULL,
  `insc_municipal` varchar(60) NOT NULL,
  `cidade` varchar(160) NOT NULL,
  `estado` varchar(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `fornecedores`
--

INSERT INTO `fornecedores` (`id`, `cnpj`, `razao_social`, `nome_fantasia`, `endereco`, `telefone`, `ativo`, `insc_municipal`, `cidade`, `estado`) VALUES
(1, '3131131', '51321232', NULL, '123123321', '123213123', 1, '123123123', '12312312', '3123123');

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico`
--

CREATE TABLE `historico` (
  `id` int(11) NOT NULL,
  `data` date NOT NULL,
  `usuario` varchar(60) NOT NULL,
  `tipo_atividade` varchar(160) NOT NULL,
  `log` text NOT NULL,
  `ip` varchar(60) NOT NULL,
  `quando` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `historico`
--

INSERT INTO `historico` (`id`, `data`, `usuario`, `tipo_atividade`, `log`, `ip`, `quando`) VALUES
(1, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '::1', '2026-03-04 11:34:17'),
(2, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '::1', '2026-03-04 18:18:04'),
(3, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:11:13'),
(4, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '::1', '2026-03-04 19:12:32'),
(5, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:26:52'),
(6, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:28:30'),
(7, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:33:43'),
(8, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:37:07'),
(9, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:44:49'),
(10, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:46:00'),
(11, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:48:14'),
(12, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 19:51:26'),
(13, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 20:10:28'),
(14, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 20:14:29'),
(15, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 20:20:28'),
(16, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 20:21:11'),
(17, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 20:23:29'),
(18, '2026-03-04', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-04 21:31:28'),
(19, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 00:11:33'),
(20, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 00:16:35'),
(21, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '::1', '2026-03-05 00:19:31'),
(22, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 01:02:53'),
(23, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 01:05:24'),
(24, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 01:25:56'),
(25, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 01:28:25'),
(26, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 01:33:31'),
(27, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 02:14:46'),
(28, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 02:14:59'),
(29, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 02:17:44'),
(30, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '::1', '2026-03-05 02:44:12'),
(31, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 02:54:55');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_venda`
--

CREATE TABLE `pagamentos_venda` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `tipo_pagamento` enum('pix','convenio','pos_debito','pos_credito','pos_pix','dinheiro','outros') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `referencia_externa` varchar(100) DEFAULT NULL,
  `descricao` varchar(150) DEFAULT NULL,
  `status` enum('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  `gerado_por` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pagamentos_venda`
--

INSERT INTO `pagamentos_venda` (`id`, `venda_id`, `tipo_pagamento`, `valor`, `referencia_externa`, `descricao`, `status`, `gerado_por`, `created_at`) VALUES
(1, 8, 'dinheiro', 10.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:04:07'),
(2, 8, 'dinheiro', 8.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:04:07'),
(3, 9, 'dinheiro', 100.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:05:06'),
(4, 10, 'dinheiro', 6.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:05:44'),
(5, 10, 'dinheiro', 0.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:05:44'),
(6, 10, 'dinheiro', 100.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:05:44'),
(7, 11, 'dinheiro', 120.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:07:09'),
(8, 12, 'dinheiro', 60.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:09:10'),
(9, 13, 'dinheiro', 5.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:10:39'),
(10, 14, 'dinheiro', 204.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:26:54'),
(11, 14, 'dinheiro', 100.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:26:54'),
(12, 15, 'dinheiro', 72.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:28:44'),
(13, 15, 'dinheiro', 10.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:28:44'),
(14, 16, 'dinheiro', 100.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 01:36:43'),
(15, 17, 'dinheiro', 78.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:15:24'),
(16, 18, 'dinheiro', 100.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:15:47'),
(17, 18, 'dinheiro', 28.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:15:47'),
(18, 18, 'dinheiro', 56.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:15:47'),
(19, 18, 'dinheiro', 112.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:15:47'),
(20, 19, 'dinheiro', 100.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:17:56'),
(21, 20, 'dinheiro', 100.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(22, 20, 'dinheiro', 28.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(23, 20, 'dinheiro', 56.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(24, 20, 'dinheiro', 112.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(25, 20, 'dinheiro', 224.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(26, 20, 'dinheiro', 448.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(27, 20, 'dinheiro', 896.00, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(28, 20, 'dinheiro', 1.79, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(29, 20, 'dinheiro', 1.79, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(30, 20, 'dinheiro', 1.80, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(31, 20, 'dinheiro', 1.80, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(32, 20, 'dinheiro', 1.80, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31'),
(33, 20, 'dinheiro', 1.80, NULL, 'Sync PDV', 'confirmado', NULL, '2026-03-05 02:55:31');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `codigo_interno_alternativo` int(11) DEFAULT NULL,
  `preco_venda` decimal(10,2) NOT NULL,
  `custo_item` decimal(10,2) NOT NULL,
  `fator_embalagem` int(11) NOT NULL DEFAULT 1,
  `unidade_base` enum('UN','G') NOT NULL DEFAULT 'UN',
  `fornecedor_id` int(11) NOT NULL,
  `ultima_alteracao` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultima_alteracao_por` int(11) NOT NULL,
  `bloqueado` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `nome`, `codigo_interno_alternativo`, `preco_venda`, `custo_item`, `fator_embalagem`, `unidade_base`, `fornecedor_id`, `ultima_alteracao`, `ultima_alteracao_por`, `bloqueado`) VALUES
(1, 'Biscoito Bauducco 120g', 123, 6.00, 2.00, 24, 'UN', 1, '2026-03-04 03:03:09', 1, 0),
(2, 'Banana', 101, 3.00, 1.00, 1, 'G', 1, '2026-03-04 09:26:26', 1, 0),
(3, 'Coca cola', 2301, 5.00, 2.00, 12, 'UN', 1, '2026-03-05 01:10:26', 1, 1),
(4, 'Coca cola2', 23012, 5.00, 2.00, 12, 'UN', 1, '2026-03-04 20:14:02', 1, 0),
(5, 'Coca cola3', 230123, 5.00, 2.00, 12, 'UN', 1, '2026-03-04 20:22:59', 1, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos_codigos_barras`
--

CREATE TABLE `produtos_codigos_barras` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `codigo_barras` varchar(50) NOT NULL,
  `tipo_embalagem` enum('UN','CX','KG','G') NOT NULL,
  `preco_venda` decimal(10,2) NOT NULL,
  `imagem_path` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos_codigos_barras`
--

INSERT INTO `produtos_codigos_barras` (`id`, `produto_id`, `codigo_barras`, `tipo_embalagem`, `preco_venda`, `imagem_path`) VALUES
(1, 1, '78999999999', 'UN', 6.00, NULL),
(2, 1, '178999999999', 'CX', 6.00, NULL),
(3, 2, '1101', 'KG', 3.00, NULL),
(4, 2, '101', 'G', 3.00, NULL),
(5, 3, '789456123', 'UN', 5.00, NULL),
(6, 3, '1789456123', 'CX', 5.00, NULL),
(7, 4, '7894561232', 'UN', 5.00, NULL),
(8, 4, '17894561232', 'CX', 5.00, NULL),
(9, 5, '78945612323', 'UN', 5.00, NULL),
(10, 5, '178945612323', 'CX', 5.00, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `supervisores_cartoes`
--

CREATE TABLE `supervisores_cartoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `codigo_cartao` varchar(100) NOT NULL,
  `descricao` varchar(200) DEFAULT NULL,
  `permite_desconto_item` tinyint(1) NOT NULL DEFAULT 0,
  `permite_desconto_venda` tinyint(1) NOT NULL DEFAULT 0,
  `permite_cancelar_item` tinyint(1) NOT NULL DEFAULT 0,
  `permite_cancelar_venda` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `login` varchar(120) NOT NULL,
  `password` text NOT NULL,
  `perfil` enum('operador','gerente','administrador') NOT NULL DEFAULT 'operador',
  `nome` text NOT NULL,
  `cpf` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `telefone` varchar(60) NOT NULL,
  `status` enum('ativado','desativado') NOT NULL DEFAULT 'ativado',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `criado_por` int(11) NOT NULL,
  `ultimo_login` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `login`, `password`, `perfil`, `nome`, `cpf`, `email`, `telefone`, `status`, `data_criacao`, `criado_por`, `ultimo_login`) VALUES
(1, '12345678999', '$2y$10$fTWJVIIlI9y.XZ4D5a2bE.x4pimv5Jg9gDdPYCF04KeJ2QyAGF9qW', 'administrador', 'Maicon', '12345678999', 'maiiconferreira.pw@gmail.com', '65993573829', 'ativado', '2026-03-04 01:44:16', 0, '2026-03-05 02:54:55');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `numero_venda` varchar(30) NOT NULL,
  `data_venda` datetime NOT NULL DEFAULT current_timestamp(),
  `cliente_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `acrescimo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('aberta','finalizada','cancelada') NOT NULL DEFAULT 'aberta',
  `observacao` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `numero_pdv` varchar(10) DEFAULT '01' COMMENT 'Número do PDV que originou a venda'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vendas`
--

INSERT INTO `vendas` (`id`, `numero_venda`, `data_venda`, `cliente_id`, `usuario_id`, `subtotal`, `desconto`, `acrescimo`, `total`, `status`, `observacao`, `created_at`, `updated_at`, `numero_pdv`) VALUES
(8, 'VND1772687044360', '2026-03-05 05:04:04', NULL, 1, 18.00, 0.00, 0.00, 18.00, 'finalizada', NULL, '2026-03-05 01:04:07', '2026-03-05 01:04:07', '01'),
(9, 'VND1772687105426', '2026-03-05 05:05:05', NULL, 1, 5.00, 0.00, 0.00, 5.00, 'finalizada', NULL, '2026-03-05 01:05:06', '2026-03-05 01:05:06', '01'),
(10, 'VND1772687135516', '2026-03-05 05:05:35', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-05 01:05:44', '2026-03-05 01:05:44', '01'),
(11, 'VND1772687226878', '2026-03-05 05:07:06', NULL, 1, 120.00, 0.00, 0.00, 120.00, 'finalizada', NULL, '2026-03-05 01:07:09', '2026-03-05 01:07:09', '01'),
(12, 'VND1772687349016', '2026-03-05 05:09:09', NULL, 1, 60.00, 0.00, 0.00, 60.00, 'finalizada', NULL, '2026-03-05 01:09:10', '2026-03-05 01:09:10', '01'),
(13, 'VND1772687437612', '2026-03-05 05:10:37', NULL, 1, 5.00, 0.00, 0.00, 5.00, 'finalizada', NULL, '2026-03-05 01:10:39', '2026-03-05 01:10:39', '01'),
(14, 'VND1772688407546', '2026-03-05 05:26:47', NULL, 1, 204.00, 0.00, 0.00, 204.00, 'finalizada', NULL, '2026-03-05 01:26:54', '2026-03-05 01:26:54', '01'),
(15, 'VND1772688518476', '2026-03-05 05:28:38', NULL, 1, 72.00, 0.00, 0.00, 72.00, 'finalizada', NULL, '2026-03-05 01:28:44', '2026-03-05 01:28:44', '01'),
(16, 'VND1772689002498', '2026-03-05 05:36:42', NULL, 1, 62.00, 0.00, 0.00, 62.00, 'finalizada', NULL, '2026-03-05 01:36:43', '2026-03-05 01:36:43', '01'),
(17, 'VND1772691313659', '2026-03-05 06:15:13', NULL, 1, 78.00, 0.00, 0.00, 78.00, 'finalizada', NULL, '2026-03-05 02:15:24', '2026-03-05 02:15:24', '01'),
(18, 'VND1772691340624', '2026-03-05 06:15:40', NULL, 1, 72.00, 0.00, 0.00, 72.00, 'finalizada', NULL, '2026-03-05 02:15:47', '2026-03-05 02:15:47', '01'),
(19, 'VND1772691474343', '2026-03-05 06:17:54', NULL, 1, 78.00, 0.00, 0.00, 78.00, 'finalizada', NULL, '2026-03-05 02:17:56', '2026-03-05 02:17:56', '01'),
(20, 'VND1772693709106', '2026-03-05 06:55:09', NULL, 1, 72.00, 0.00, 0.00, 72.00, 'finalizada', NULL, '2026-03-05 02:55:31', '2026-03-05 02:55:31', '01');

-- --------------------------------------------------------

--
-- Estrutura para tabela `venda_itens`
--

CREATE TABLE `venda_itens` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` decimal(10,3) NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `venda_itens`
--

INSERT INTO `venda_itens` (`id`, `venda_id`, `produto_id`, `quantidade`, `valor_unitario`, `subtotal`, `created_at`) VALUES
(8, 8, 1, 3.000, 6.00, 18.00, '2026-03-05 01:04:07'),
(9, 9, 3, 1.000, 5.00, 5.00, '2026-03-05 01:05:06'),
(10, 10, 1, 1.000, 6.00, 6.00, '2026-03-05 01:05:44'),
(11, 11, 1, 20.000, 6.00, 120.00, '2026-03-05 01:07:09'),
(12, 12, 3, 12.000, 5.00, 60.00, '2026-03-05 01:09:10'),
(13, 13, 3, 1.000, 5.00, 5.00, '2026-03-05 01:10:39'),
(14, 14, 1, 24.000, 6.00, 144.00, '2026-03-05 01:26:54'),
(15, 14, 3, 12.000, 5.00, 60.00, '2026-03-05 01:26:54'),
(16, 15, 1, 12.000, 6.00, 72.00, '2026-03-05 01:28:44'),
(17, 16, 1, 12.000, 6.00, 62.00, '2026-03-05 01:36:43'),
(18, 17, 1, 13.000, 6.00, 78.00, '2026-03-05 02:15:24'),
(19, 18, 1, 12.000, 6.00, 72.00, '2026-03-05 02:15:47'),
(20, 19, 1, 13.000, 6.00, 78.00, '2026-03-05 02:17:56'),
(21, 20, 1, 12.000, 6.00, 72.00, '2026-03-05 02:55:31');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `caixa_sangrias`
--
ALTER TABLE `caixa_sangrias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessao` (`caixa_sessao_id`),
  ADD KEY `fk_sang_usuario` (`usuario_id`);

--
-- Índices de tabela `caixa_sessoes`
--
ALTER TABLE `caixa_sessoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_abertura` (`abertura_em`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_clientes_cpf` (`cpf`);

--
-- Índices de tabela `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`chave`);

--
-- Índices de tabela `estoque`
--
ALTER TABLE `estoque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Índices de tabela `historico`
--
ALTER TABLE `historico`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `pagamentos_venda`
--
ALTER TABLE `pagamentos_venda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `tipo_pagamento` (`tipo_pagamento`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `produtos_codigos_barras`
--
ALTER TABLE `produtos_codigos_barras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_produto_tipo` (`produto_id`,`tipo_embalagem`);

--
-- Índices de tabela `supervisores_cartoes`
--
ALTER TABLE `supervisores_cartoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codigo_cartao` (`codigo_cartao`),
  ADD KEY `fk_sup_cartao_usuario` (`usuario_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `data_venda` (`data_venda`);

--
-- Índices de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `caixa_sangrias`
--
ALTER TABLE `caixa_sangrias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `caixa_sessoes`
--
ALTER TABLE `caixa_sessoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `estoque`
--
ALTER TABLE `estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `historico`
--
ALTER TABLE `historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de tabela `pagamentos_venda`
--
ALTER TABLE `pagamentos_venda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `produtos_codigos_barras`
--
ALTER TABLE `produtos_codigos_barras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `supervisores_cartoes`
--
ALTER TABLE `supervisores_cartoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `caixa_sangrias`
--
ALTER TABLE `caixa_sangrias`
  ADD CONSTRAINT `fk_sang_sessao` FOREIGN KEY (`caixa_sessao_id`) REFERENCES `caixa_sessoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sang_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `caixa_sessoes`
--
ALTER TABLE `caixa_sessoes`
  ADD CONSTRAINT `fk_cs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `estoque`
--
ALTER TABLE `estoque`
  ADD CONSTRAINT `estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  ADD CONSTRAINT `estoque_movimentacoes_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `pagamentos_venda`
--
ALTER TABLE `pagamentos_venda`
  ADD CONSTRAINT `pagamentos_venda_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`);

--
-- Restrições para tabelas `produtos_codigos_barras`
--
ALTER TABLE `produtos_codigos_barras`
  ADD CONSTRAINT `produtos_codigos_barras_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `supervisores_cartoes`
--
ALTER TABLE `supervisores_cartoes`
  ADD CONSTRAINT `fk_sup_cartao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD CONSTRAINT `venda_itens_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
