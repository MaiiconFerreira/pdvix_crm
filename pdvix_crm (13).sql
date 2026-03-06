-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 06/03/2026 às 14:21
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

--
-- Despejando dados para a tabela `caixa_sangrias`
--

INSERT INTO `caixa_sangrias` (`id`, `caixa_sessao_id`, `usuario_id`, `valor`, `motivo`, `data_hora`, `created_at`) VALUES
(1, 9, 1, 112.00, '', '2026-03-06 04:14:50', '2026-03-06 00:15:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixa_sessoes`
--

CREATE TABLE `caixa_sessoes` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) DEFAULT 1,
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

INSERT INTO `caixa_sessoes` (`id`, `loja_id`, `numero_pdv`, `usuario_id`, `abertura_em`, `fechamento_em`, `valor_abertura`, `total_dinheiro`, `total_pix`, `total_debito`, `total_credito`, `total_convenio`, `total_outros`, `total_vendas`, `total_canceladas`, `total_sangrias`, `saldo_esperado`, `caixa_contado`, `diferenca`, `status`, `observacao`, `sincronizado_em`, `created_at`, `updated_at`) VALUES
(1, 1, '01', 1, '2026-03-05 06:17:46', '2026-03-05 06:18:14', 0.00, 78.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, 0, 0.00, 78.00, NULL, NULL, 'fechado', NULL, '2026-03-05 02:18:14', '2026-03-05 02:18:14', '2026-03-05 02:18:14'),
(2, 1, '01', 1, '2026-03-05 06:54:57', '2026-03-05 06:55:55', 100.00, 936.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, 0, 0.00, 1036.00, NULL, NULL, 'fechado', NULL, '2026-03-05 02:55:55', '2026-03-05 02:55:55', '2026-03-05 02:55:55'),
(3, 1, '01', 1, '2026-03-06 00:05:50', '2026-03-06 00:12:37', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0.00, 0.00, NULL, NULL, 'fechado', NULL, '2026-03-05 20:12:37', '2026-03-05 20:12:37', '2026-03-05 20:12:37'),
(4, 1, '01', 1, '2026-03-06 01:00:04', '2026-03-06 01:01:41', 0.00, 12.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, 0, 0.00, 12.00, NULL, NULL, 'fechado', NULL, '2026-03-05 21:01:41', '2026-03-05 21:01:41', '2026-03-05 21:01:41'),
(5, 1, '01', 1, '2026-03-06 01:06:41', '2026-03-06 01:06:55', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0.00, 0.00, NULL, NULL, 'fechado', NULL, '2026-03-05 21:06:55', '2026-03-05 21:06:55', '2026-03-05 21:06:55'),
(6, 1, '01', 1, '2026-03-06 01:36:58', '2026-03-06 01:38:05', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0.00, 0.00, NULL, NULL, 'fechado', NULL, '2026-03-05 21:38:05', '2026-03-05 21:38:05', '2026-03-05 21:38:05'),
(7, 1, '01', 1, '2026-03-06 01:51:25', '2026-03-06 03:04:39', 0.00, 18.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, 0, 0.00, 18.00, NULL, NULL, 'fechado', NULL, '2026-03-05 23:04:39', '2026-03-05 23:04:39', '2026-03-05 23:04:39'),
(8, 1, '01', 1, '2026-03-06 04:04:25', '2026-03-06 04:12:35', 0.00, 6.00, 606.00, 0.00, 0.00, 0.00, 0.00, 2, 0, 0.00, 6.00, NULL, NULL, 'fechado', NULL, '2026-03-06 00:12:35', '2026-03-06 00:12:35', '2026-03-06 00:12:35'),
(9, 1, '01', 1, '2026-03-06 04:13:46', '2026-03-06 04:15:04', 100.00, 12.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, 0, 112.00, 0.00, NULL, NULL, 'fechado', NULL, '2026-03-06 00:15:04', '2026-03-06 00:15:04', '2026-03-06 00:15:04'),
(10, 1, '01', 1, '2026-03-06 04:17:36', '2026-03-06 05:15:38', 100.00, 18.00, 6.00, 0.00, 0.00, 0.00, 0.00, 4, 0, 0.00, 118.00, NULL, NULL, 'fechado', NULL, '2026-03-06 01:15:38', '2026-03-06 01:15:38', '2026-03-06 01:15:38');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cancelamentos`
--

CREATE TABLE `cancelamentos` (
  `id` int(11) NOT NULL,
  `tipo` enum('venda','item') NOT NULL,
  `venda_id` int(11) NOT NULL,
  `venda_item_id` int(11) DEFAULT NULL,
  `loja_id` int(11) NOT NULL,
  `numero_pdv` varchar(10) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  `valor_cancelado` decimal(10,2) NOT NULL,
  `cancelado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `origem` enum('pdv','painel') NOT NULL DEFAULT 'pdv',
  `sincronizado` tinyint(1) DEFAULT 0,
  `sync_key` varchar(80) GENERATED ALWAYS AS (concat(`tipo`,':',`venda_id`,':',coalesce(`venda_item_id`,0))) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Estrutura para tabela `comandas`
--

CREATE TABLE `comandas` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `numero` varchar(30) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `cliente_nome` varchar(160) DEFAULT 'CONSUMIDOR FINAL',
  `usuario_id` int(11) NOT NULL,
  `pdv_destino` varchar(10) DEFAULT NULL,
  `status` enum('aberta','enviada','finalizada','cancelada') DEFAULT 'aberta',
  `observacao` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `comandas`
--

INSERT INTO `comandas` (`id`, `loja_id`, `numero`, `cliente_id`, `cliente_nome`, `usuario_id`, `pdv_destino`, `status`, `observacao`, `created_at`, `updated_at`) VALUES
(1, 1, 'CMD-20260305-0001', NULL, '123', 1, '01', 'enviada', NULL, '2026-03-05 20:57:22', '2026-03-05 20:57:38'),
(2, 1, 'CMD-20260305-0002', NULL, 'teste', 1, '01', 'enviada', NULL, '2026-03-05 22:07:17', '2026-03-05 22:07:23'),
(3, 1, 'CMD-20260306-0001', NULL, 'Maicon', 1, '01', 'enviada', NULL, '2026-03-06 01:08:30', '2026-03-06 01:08:36'),
(4, 1, 'CMD-20260306-0002', NULL, '123123', 1, '01', 'enviada', NULL, '2026-03-06 01:56:32', '2026-03-06 01:56:35');

-- --------------------------------------------------------

--
-- Estrutura para tabela `comanda_itens`
--

CREATE TABLE `comanda_itens` (
  `id` int(11) NOT NULL,
  `comanda_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` decimal(10,3) NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `comanda_itens`
--

INSERT INTO `comanda_itens` (`id`, `comanda_id`, `produto_id`, `quantidade`, `valor_unitario`, `subtotal`, `observacao`) VALUES
(1, 1, 2, 1.000, 3.00, 3.00, NULL),
(2, 2, 1, 5.000, 6.00, 30.00, NULL),
(3, 3, 1, 1.000, 6.00, 6.00, NULL),
(4, 4, 1, 1.000, 6.00, 6.00, NULL);

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
  `loja_id` int(11) DEFAULT 1,
  `quantidade_atual` int(11) NOT NULL DEFAULT 0,
  `data_atualizacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `estoque`
--

INSERT INTO `estoque` (`id`, `produto_id`, `loja_id`, `quantidade_atual`, `data_atualizacao`) VALUES
(4, 1, 1, 35, '2026-03-06 03:14:49'),
(5, 2, 1, 700, '2026-03-04 09:27:50'),
(6, 3, 1, 0, '2026-03-05 01:26:54');

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
(24, 1, 'ENTRADA', 2.000, 'CX', '', '', NULL, '2026-03-05 20:04:52', 1, 'COMPRA'),
(25, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000006', 27, '2026-03-06 01:07:22', 1, 'VENDA'),
(26, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000007', 28, '2026-03-06 01:09:06', 1, 'VENDA'),
(27, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000008', 29, '2026-03-06 01:13:09', 1, 'VENDA'),
(28, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000010', 30, '2026-03-06 01:54:40', 1, 'VENDA'),
(29, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000011', 31, '2026-03-06 01:58:29', 1, 'VENDA'),
(30, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000012', 32, '2026-03-06 02:00:52', 1, 'VENDA'),
(31, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000013', 33, '2026-03-06 02:02:48', 1, 'VENDA'),
(32, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000016', 34, '2026-03-06 02:17:25', 1, 'VENDA'),
(33, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000017', 35, '2026-03-06 02:24:10', 1, 'VENDA'),
(34, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000018', 36, '2026-03-06 02:26:17', 1, 'VENDA'),
(35, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000019', 37, '2026-03-06 02:53:14', 1, 'VENDA'),
(36, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000020', 38, '2026-03-06 02:56:20', 1, 'VENDA'),
(37, 1, 'SAIDA', 1.000, 'UN', '123', 'Sync PDV offline — LOJA1-PDV01-20260306-000021', 39, '2026-03-06 03:14:49', 1, 'VENDA');

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
(31, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 02:54:55'),
(32, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '::1', '2026-03-05 17:55:25'),
(33, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 20:05:47'),
(34, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 21:00:01'),
(35, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 21:06:40'),
(36, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 21:36:56'),
(37, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 21:51:23'),
(38, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 21:57:43'),
(39, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 22:06:31'),
(40, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 22:09:36'),
(41, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-05 23:02:38'),
(42, '2026-03-05', '12345678999', 'login', 'Efetuou login no sistema.', '::1', '2026-03-05 23:04:07'),
(43, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 00:04:23'),
(44, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 00:10:34'),
(45, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 00:13:43'),
(46, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 00:17:35'),
(47, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 01:06:45'),
(48, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 01:16:59'),
(49, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 01:20:08'),
(50, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 01:54:14'),
(51, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 02:04:00'),
(52, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 02:17:04'),
(53, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 02:23:58'),
(54, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 02:26:09'),
(55, '2026-03-06', '12345678999', 'login', 'Efetuou login no sistema.', '127.0.0.1', '2026-03-06 02:42:49');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lojas`
--

CREATE TABLE `lojas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cnpj` varchar(18) NOT NULL,
  `endereco` text NOT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `bairro` varchar(80) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `status` enum('ativa','inativa') NOT NULL DEFAULT 'ativa',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `lojas`
--

INSERT INTO `lojas` (`id`, `nome`, `cnpj`, `endereco`, `numero`, `bairro`, `cidade`, `estado`, `cep`, `telefone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Distribuidora Copacobana', '', '', '', NULL, '', '', '', '', '', '2026-03-05 19:30:30', '2026-03-05 21:24:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `loja_usuarios`
--

CREATE TABLE `loja_usuarios` (
  `loja_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `loja_usuarios`
--

INSERT INTO `loja_usuarios` (`loja_id`, `usuario_id`) VALUES
(1, 1);

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
  `pagarme_order_id` varchar(100) DEFAULT NULL,
  `pagarme_charge_id` varchar(100) DEFAULT NULL,
  `descricao` varchar(150) DEFAULT NULL,
  `status` enum('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  `gerado_por` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pagamentos_venda`
--

INSERT INTO `pagamentos_venda` (`id`, `venda_id`, `tipo_pagamento`, `valor`, `referencia_externa`, `pagarme_order_id`, `pagarme_charge_id`, `descricao`, `status`, `gerado_por`, `created_at`) VALUES
(34, 27, 'dinheiro', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 01:07:22'),
(35, 28, 'pix', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 01:09:06'),
(36, 29, 'dinheiro', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 01:13:09'),
(37, 30, 'pix', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 01:54:40'),
(38, 31, 'pos_pix', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 01:58:29'),
(39, 32, 'pix', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 02:00:52'),
(40, 33, 'pix', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 02:02:48'),
(41, 34, 'pix', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 02:17:25'),
(42, 35, 'pix', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 02:24:10'),
(43, 36, 'pix', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 02:26:17'),
(44, 37, 'dinheiro', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 02:53:14'),
(45, 38, 'dinheiro', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 02:56:20'),
(46, 39, 'dinheiro', 0.01, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 03:14:49'),
(47, 39, 'outros', 6.00, NULL, NULL, NULL, 'Sync PDV — 01', 'confirmado', NULL, '2026-03-06 03:14:49');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagarme_transacoes`
--

CREATE TABLE `pagarme_transacoes` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL DEFAULT 1,
  `order_id` varchar(100) NOT NULL,
  `charge_id` varchar(100) DEFAULT NULL,
  `tipo` enum('pix','pos_debito','pos_credito','pos_pix') NOT NULL DEFAULT 'pix',
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `qr_code` text DEFAULT NULL,
  `qr_code_url` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `payload_request` longtext DEFAULT NULL,
  `payload_response` longtext DEFAULT NULL,
  `payload_webhook` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pagarme_transacoes`
--

INSERT INTO `pagarme_transacoes` (`id`, `venda_id`, `loja_id`, `order_id`, `charge_id`, `tipo`, `status`, `qr_code`, `qr_code_url`, `expires_at`, `payload_request`, `payload_response`, `payload_webhook`, `created_at`, `updated_at`) VALUES
(1, 41, 1, 'or_oVeG1Beirgc5k7AO', 'ch_Rjy9ZB3C8U7Q9xkB', 'pix', 'failed', NULL, NULL, '2026-03-06 03:17:34', '{\"code\":\"PDVIX-41-1772780854\",\"closed\":true,\"items\":[{\"amount\":600,\"description\":\"Venda PDVix #41\",\"quantity\":1,\"code\":\"VENDA-41\"}],\"payments\":[{\"payment_method\":\"pix\",\"pix\":{\"expires_in\":600},\"amount\":600}],\"customer\":{\"name\":\"Consumidor Final\",\"type\":\"individual\",\"email\":\"consumidor@pdvix.local\",\"document\":\"00000000000\",\"document_type\":\"CPF\",\"phones\":{\"home_phone\":{\"country_code\":\"55\",\"area_code\":\"65\",\"number\":\"999999999\"}},\"address\":{\"line_1\":\"PDV Local\",\"zip_code\":\"78000000\",\"city\":\"Cuiaba\",\"state\":\"MT\",\"country\":\"BR\"}}}', '{\"id\":\"or_oVeG1Beirgc5k7AO\",\"code\":\"PDVIX-41-1772780854\",\"amount\":600,\"currency\":\"BRL\",\"closed\":true,\"items\":[{\"id\":\"oi_Vl4okXxHGHq5kmW1\",\"type\":\"product\",\"description\":\"Venda PDVix #41\",\"amount\":600,\"quantity\":1,\"status\":\"active\",\"created_at\":\"2026-03-06T07:07:34Z\",\"updated_at\":\"2026-03-06T07:07:34Z\",\"code\":\"VENDA-41\"}],\"customer\":{\"id\":\"cus_O15zpQNOHJsdbw6k\",\"name\":\"Consumidor Final\",\"email\":\"consumidor@pdvix.local\",\"document\":\"00000000000\",\"document_type\":\"cpf\",\"type\":\"individual\",\"delinquent\":false,\"address\":{\"id\":\"addr_3mQeY0zUgS66N2GY\",\"line_1\":\"PDV Local\",\"zip_code\":\"78000000\",\"city\":\"Cuiaba\",\"state\":\"MT\",\"country\":\"BR\",\"status\":\"active\",\"created_at\":\"2026-03-06T07:07:34Z\",\"updated_at\":\"2026-03-06T07:07:34Z\"},\"created_at\":\"2026-03-06T07:07:34Z\",\"updated_at\":\"2026-03-06T07:07:34Z\",\"phones\":{\"home_phone\":{\"country_code\":\"55\",\"number\":\"999999999\",\"area_code\":\"65\"}}},\"status\":\"failed\",\"created_at\":\"2026-03-06T07:07:34Z\",\"updated_at\":\"2026-03-06T07:07:34Z\",\"closed_at\":\"2026-03-06T07:07:34Z\",\"charges\":[{\"id\":\"ch_Rjy9ZB3C8U7Q9xkB\",\"code\":\"PDVIX-41-1772780854\",\"amount\":600,\"status\":\"failed\",\"currency\":\"BRL\",\"payment_method\":\"pix\",\"created_at\":\"2026-03-06T07:07:34Z\",\"updated_at\":\"2026-03-06T07:07:34Z\",\"customer\":{\"id\":\"cus_O15zpQNOHJsdbw6k\",\"name\":\"Consumidor Final\",\"email\":\"consumidor@pdvix.local\",\"document\":\"00000000000\",\"document_type\":\"cpf\",\"type\":\"individual\",\"delinquent\":false,\"address\":{\"id\":\"addr_3mQeY0zUgS66N2GY\",\"line_1\":\"PDV Local\",\"zip_code\":\"78000000\",\"city\":\"Cuiaba\",\"state\":\"MT\",\"country\":\"BR\",\"status\":\"active\",\"created_at\":\"2026-03-06T07:07:34Z\",\"updated_at\":\"2026-03-06T07:07:34Z\"},\"created_at\":\"2026-03-06T07:07:34Z\",\"updated_at\":\"2026-03-06T07:07:34Z\",\"phones\":{\"home_phone\":{\"country_code\":\"55\",\"number\":\"999999999\",\"area_code\":\"65\"}}},\"last_transaction\":{\"expires_at\":\"2026-03-06T07:17:34Z\",\"id\":\"tran_RaBQODvUkaCZ7YOl\",\"transaction_type\":\"pix\",\"amount\":600,\"status\":\"failed\",\"success\":false,\"created_at\":\"2026-03-06T07:07:34Z\",\"updated_at\":\"2026-03-06T07:07:34Z\",\"gateway_response\":{\"code\":\"404\",\"errors\":[{\"message\":\"not_found |  | N\\u00e3o foi poss\\u00edvel encontrar os dados da Company desejada\"}]},\"antifraud_response\":[],\"metadata\":[]}}]}', NULL, '2026-03-06 03:07:35', '2026-03-06 03:07:35'),
(2, 41, 1, 'or_JqDxpY0eigCP4yNz', 'ch_yr9nRK0iyc0E1QXK', 'pix', 'failed', NULL, NULL, '2026-03-06 03:19:36', '{\"code\":\"PDVIX-41-1772780976\",\"closed\":true,\"items\":[{\"amount\":599,\"description\":\"Venda PDVix #41\",\"quantity\":1,\"code\":\"VENDA-41\"}],\"payments\":[{\"payment_method\":\"pix\",\"pix\":{\"expires_in\":600},\"amount\":599}],\"customer\":{\"name\":\"Consumidor Final\",\"type\":\"individual\",\"email\":\"consumidor@pdvix.local\",\"document\":\"00000000000\",\"document_type\":\"CPF\",\"phones\":{\"home_phone\":{\"country_code\":\"55\",\"area_code\":\"65\",\"number\":\"999999999\"}},\"address\":{\"line_1\":\"PDV Local\",\"zip_code\":\"78000000\",\"city\":\"Cuiaba\",\"state\":\"MT\",\"country\":\"BR\"}}}', '{\"id\":\"or_JqDxpY0eigCP4yNz\",\"code\":\"PDVIX-41-1772780976\",\"amount\":599,\"currency\":\"BRL\",\"closed\":true,\"items\":[{\"id\":\"oi_mDjO2xZS8uQBqM37\",\"type\":\"product\",\"description\":\"Venda PDVix #41\",\"amount\":599,\"quantity\":1,\"status\":\"active\",\"created_at\":\"2026-03-06T07:09:36Z\",\"updated_at\":\"2026-03-06T07:09:36Z\",\"code\":\"VENDA-41\"}],\"customer\":{\"id\":\"cus_93o0VyT1gCrGp2Nv\",\"name\":\"Consumidor Final\",\"email\":\"consumidor@pdvix.local\",\"document\":\"00000000000\",\"document_type\":\"cpf\",\"type\":\"individual\",\"delinquent\":false,\"address\":{\"id\":\"addr_jEW6ogWilOspm6m4\",\"line_1\":\"PDV Local\",\"zip_code\":\"78000000\",\"city\":\"Cuiaba\",\"state\":\"MT\",\"country\":\"BR\",\"status\":\"active\",\"created_at\":\"2026-03-06T07:09:36Z\",\"updated_at\":\"2026-03-06T07:09:36Z\"},\"created_at\":\"2026-03-06T07:09:36Z\",\"updated_at\":\"2026-03-06T07:09:36Z\",\"phones\":{\"home_phone\":{\"country_code\":\"55\",\"number\":\"999999999\",\"area_code\":\"65\"}}},\"status\":\"failed\",\"created_at\":\"2026-03-06T07:09:36Z\",\"updated_at\":\"2026-03-06T07:09:36Z\",\"closed_at\":\"2026-03-06T07:09:36Z\",\"charges\":[{\"id\":\"ch_yr9nRK0iyc0E1QXK\",\"code\":\"PDVIX-41-1772780976\",\"amount\":599,\"status\":\"failed\",\"currency\":\"BRL\",\"payment_method\":\"pix\",\"created_at\":\"2026-03-06T07:09:36Z\",\"updated_at\":\"2026-03-06T07:09:36Z\",\"customer\":{\"id\":\"cus_93o0VyT1gCrGp2Nv\",\"name\":\"Consumidor Final\",\"email\":\"consumidor@pdvix.local\",\"document\":\"00000000000\",\"document_type\":\"cpf\",\"type\":\"individual\",\"delinquent\":false,\"address\":{\"id\":\"addr_jEW6ogWilOspm6m4\",\"line_1\":\"PDV Local\",\"zip_code\":\"78000000\",\"city\":\"Cuiaba\",\"state\":\"MT\",\"country\":\"BR\",\"status\":\"active\",\"created_at\":\"2026-03-06T07:09:36Z\",\"updated_at\":\"2026-03-06T07:09:36Z\"},\"created_at\":\"2026-03-06T07:09:36Z\",\"updated_at\":\"2026-03-06T07:09:36Z\",\"phones\":{\"home_phone\":{\"country_code\":\"55\",\"number\":\"999999999\",\"area_code\":\"65\"}}},\"last_transaction\":{\"expires_at\":\"2026-03-06T07:19:36Z\",\"id\":\"tran_QrR5eRaHYeURdEqK\",\"transaction_type\":\"pix\",\"amount\":599,\"status\":\"failed\",\"success\":false,\"created_at\":\"2026-03-06T07:09:36Z\",\"updated_at\":\"2026-03-06T07:09:36Z\",\"gateway_response\":{\"code\":\"400\",\"errors\":[{\"message\":\"action_forbidden |  | Erro no gateway\"}]},\"antifraud_response\":[],\"metadata\":[]}}]}', NULL, '2026-03-06 03:09:37', '2026-03-06 03:09:37');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pdvs`
--

CREATE TABLE `pdvs` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `numero_pdv` varchar(10) NOT NULL,
  `descricao` varchar(100) DEFAULT NULL,
  `url_local` varchar(200) DEFAULT NULL,
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `ultimo_ping` datetime DEFAULT NULL,
  `online` tinyint(4) DEFAULT 0,
  `versao_app` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pdvs`
--

INSERT INTO `pdvs` (`id`, `loja_id`, `numero_pdv`, `descricao`, `url_local`, `status`, `ultimo_ping`, `online`, `versao_app`, `created_at`) VALUES
(1, 1, '01', 'PDV Principal', NULL, 'ativo', '2026-03-06 03:15:05', 0, '2.0.0', '2026-03-05 19:30:44');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) DEFAULT 1,
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

INSERT INTO `produtos` (`id`, `loja_id`, `nome`, `codigo_interno_alternativo`, `preco_venda`, `custo_item`, `fator_embalagem`, `unidade_base`, `fornecedor_id`, `ultima_alteracao`, `ultima_alteracao_por`, `bloqueado`) VALUES
(1, 1, 'Biscoito Bauducco 120g', 123, 6.00, 2.00, 24, 'UN', 1, '2026-03-04 03:03:09', 1, 0),
(2, 1, 'Banana', 101, 3.00, 1.00, 1, 'G', 1, '2026-03-04 09:26:26', 1, 0),
(3, 1, 'Coca cola', 2301, 5.00, 2.00, 12, 'UN', 1, '2026-03-05 01:10:26', 1, 1),
(4, 1, 'Coca cola2', 23012, 5.00, 2.00, 12, 'UN', 1, '2026-03-04 20:14:02', 1, 0),
(5, 1, 'Coca cola3', 230123, 5.00, 2.00, 12, 'UN', 1, '2026-03-04 20:22:59', 1, 0);

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
  `loja_id` int(11) DEFAULT 1,
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
(1, '12345678999', '$2y$10$fTWJVIIlI9y.XZ4D5a2bE.x4pimv5Jg9gDdPYCF04KeJ2QyAGF9qW', 'administrador', 'Maicon', '12345678999', 'maiiconferreira.pw@gmail.com', '65993573829', 'ativado', '2026-03-04 01:44:16', 0, '2026-03-06 02:42:49');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) DEFAULT 1,
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

INSERT INTO `vendas` (`id`, `loja_id`, `numero_venda`, `data_venda`, `cliente_id`, `usuario_id`, `subtotal`, `desconto`, `acrescimo`, `total`, `status`, `observacao`, `created_at`, `updated_at`, `numero_pdv`) VALUES
(27, 1, 'LOJA1-PDV01-20260306-000006', '2026-03-06 05:07:21', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 01:07:22', '2026-03-06 01:07:22', '01'),
(28, 1, 'LOJA1-PDV01-20260306-000007', '2026-03-06 05:09:05', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 01:09:06', '2026-03-06 01:09:06', '01'),
(29, 1, 'LOJA1-PDV01-20260306-000008', '2026-03-06 05:13:08', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 01:13:09', '2026-03-06 01:13:09', '01'),
(30, 1, 'LOJA1-PDV01-20260306-000010', '2026-03-06 05:54:25', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 01:54:40', '2026-03-06 01:54:40', '01'),
(31, 1, 'LOJA1-PDV01-20260306-000011', '2026-03-06 05:58:10', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 01:58:29', '2026-03-06 01:58:29', '01'),
(32, 1, 'LOJA1-PDV01-20260306-000012', '2026-03-06 05:58:38', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 02:00:52', '2026-03-06 02:00:52', '01'),
(33, 1, 'LOJA1-PDV01-20260306-000013', '2026-03-06 06:01:01', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 02:02:48', '2026-03-06 02:02:48', '01'),
(34, 1, 'LOJA1-PDV01-20260306-000016', '2026-03-06 06:17:22', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 02:17:25', '2026-03-06 02:17:25', '01'),
(35, 1, 'LOJA1-PDV01-20260306-000017', '2026-03-06 06:24:07', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 02:24:10', '2026-03-06 02:24:10', '01'),
(36, 1, 'LOJA1-PDV01-20260306-000018', '2026-03-06 06:26:16', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 02:26:17', '2026-03-06 02:26:17', '01'),
(37, 1, 'LOJA1-PDV01-20260306-000019', '2026-03-06 06:43:01', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 02:53:14', '2026-03-06 02:53:14', '01'),
(38, 1, 'LOJA1-PDV01-20260306-000020', '2026-03-06 06:53:27', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 02:56:20', '2026-03-06 02:56:20', '01'),
(39, 1, 'LOJA1-PDV01-20260306-000021', '2026-03-06 06:56:32', NULL, 1, 6.00, 0.00, 0.00, 6.00, 'finalizada', NULL, '2026-03-06 03:14:49', '2026-03-06 03:14:49', '01');

-- --------------------------------------------------------

--
-- Estrutura para tabela `venda_itens`
--

CREATE TABLE `venda_itens` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `produto_nome` varchar(200) DEFAULT NULL,
  `quantidade` decimal(10,3) NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL,
  `desconto_item` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  `codigo_barras_usado` varchar(50) DEFAULT '',
  `unidade_origem` varchar(10) DEFAULT 'UN',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `venda_itens`
--

INSERT INTO `venda_itens` (`id`, `venda_id`, `produto_id`, `produto_nome`, `quantidade`, `valor_unitario`, `desconto_item`, `subtotal`, `codigo_barras_usado`, `unidade_origem`, `created_at`) VALUES
(22, 27, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 01:07:22'),
(23, 28, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 01:09:06'),
(24, 29, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 01:13:09'),
(25, 30, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 01:54:40'),
(26, 31, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 01:58:29'),
(27, 32, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 02:00:52'),
(28, 33, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 02:02:48'),
(29, 34, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 02:17:25'),
(30, 35, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 02:24:10'),
(31, 36, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 02:26:17'),
(32, 37, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 02:53:14'),
(33, 38, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 02:56:20'),
(34, 39, 1, 'Biscoito Bauducco 120g', 1.000, 6.00, 0.00, 6.00, '123', 'UN', '2026-03-06 03:14:49');

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
  ADD KEY `idx_abertura` (`abertura_em`),
  ADD KEY `idx_caixa_loja_abertura` (`loja_id`,`abertura_em`);

--
-- Índices de tabela `cancelamentos`
--
ALTER TABLE `cancelamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cancelamento_sync_key` (`sync_key`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `idx_cancelamentos_loja_data` (`loja_id`,`cancelado_em`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_clientes_cpf` (`cpf`);

--
-- Índices de tabela `comandas`
--
ALTER TABLE `comandas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `loja_id` (`loja_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `comanda_itens`
--
ALTER TABLE `comanda_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comanda_id` (`comanda_id`),
  ADD KEY `produto_id` (`produto_id`);

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
-- Índices de tabela `lojas`
--
ALTER TABLE `lojas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Índices de tabela `loja_usuarios`
--
ALTER TABLE `loja_usuarios`
  ADD PRIMARY KEY (`loja_id`,`usuario_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `pagamentos_venda`
--
ALTER TABLE `pagamentos_venda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `tipo_pagamento` (`tipo_pagamento`);

--
-- Índices de tabela `pagarme_transacoes`
--
ALTER TABLE `pagarme_transacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_id` (`order_id`),
  ADD KEY `idx_venda_id` (`venda_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `pdvs`
--
ALTER TABLE `pdvs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_loja_pdv` (`loja_id`,`numero_pdv`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_produto_loja` (`loja_id`);

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
  ADD KEY `fk_sup_cartao_usuario` (`usuario_id`),
  ADD KEY `fk_supcard_loja` (`loja_id`);

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
  ADD KEY `data_venda` (`data_venda`),
  ADD KEY `idx_vendas_loja_data` (`loja_id`,`data_venda`),
  ADD KEY `idx_vendas_numero` (`numero_venda`);

--
-- Índices de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `idx_vi_venda` (`venda_id`),
  ADD KEY `idx_vi_produto` (`produto_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `caixa_sangrias`
--
ALTER TABLE `caixa_sangrias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `caixa_sessoes`
--
ALTER TABLE `caixa_sessoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `cancelamentos`
--
ALTER TABLE `cancelamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `comandas`
--
ALTER TABLE `comandas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `comanda_itens`
--
ALTER TABLE `comanda_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `estoque`
--
ALTER TABLE `estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `historico`
--
ALTER TABLE `historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de tabela `lojas`
--
ALTER TABLE `lojas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `pagamentos_venda`
--
ALTER TABLE `pagamentos_venda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT de tabela `pagarme_transacoes`
--
ALTER TABLE `pagarme_transacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `pdvs`
--
ALTER TABLE `pdvs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

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
  ADD CONSTRAINT `fk_caixa_loja` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`),
  ADD CONSTRAINT `fk_cs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `cancelamentos`
--
ALTER TABLE `cancelamentos`
  ADD CONSTRAINT `cancelamentos_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`),
  ADD CONSTRAINT `cancelamentos_ibfk_2` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`);

--
-- Restrições para tabelas `comandas`
--
ALTER TABLE `comandas`
  ADD CONSTRAINT `comandas_ibfk_1` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`),
  ADD CONSTRAINT `comandas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `comanda_itens`
--
ALTER TABLE `comanda_itens`
  ADD CONSTRAINT `comanda_itens_ibfk_1` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comanda_itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

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
-- Restrições para tabelas `loja_usuarios`
--
ALTER TABLE `loja_usuarios`
  ADD CONSTRAINT `loja_usuarios_ibfk_1` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loja_usuarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pagamentos_venda`
--
ALTER TABLE `pagamentos_venda`
  ADD CONSTRAINT `pagamentos_venda_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`);

--
-- Restrições para tabelas `pdvs`
--
ALTER TABLE `pdvs`
  ADD CONSTRAINT `pdvs_ibfk_1` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_produto_loja` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`);

--
-- Restrições para tabelas `produtos_codigos_barras`
--
ALTER TABLE `produtos_codigos_barras`
  ADD CONSTRAINT `produtos_codigos_barras_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `supervisores_cartoes`
--
ALTER TABLE `supervisores_cartoes`
  ADD CONSTRAINT `fk_sup_cartao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_supcard_loja` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`);

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `fk_venda_loja` FOREIGN KEY (`loja_id`) REFERENCES `lojas` (`id`);

--
-- Restrições para tabelas `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD CONSTRAINT `venda_itens_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
