-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 04/03/2026 às 14:33
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
(4, 1, 23, '2026-03-04 07:53:01'),
(5, 2, 700, '2026-03-04 09:27:50');

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
(8, 2, 'SAIDA', 300.000, 'G', '', '', NULL, '2026-03-04 09:27:50', 1, 'VENDA');

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
  `gerado_por` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 'Banana', 101, 3.00, 1.00, 1, 'G', 1, '2026-03-04 09:26:26', 1, 0);

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
(4, 2, '101', 'G', 3.00, NULL);

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
(1, '12345678999', '$2y$10$fTWJVIIlI9y.XZ4D5a2bE.x4pimv5Jg9gDdPYCF04KeJ2QyAGF9qW', 'administrador', 'Maicon', '12345678999', 'maiiconferreira.pw@gmail.com', '65993573829', 'ativado', '2026-03-04 01:44:16', 0, '2026-03-04 07:50:13');

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Índices para tabelas despejadas
--

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
  ADD KEY `produto_id` (`produto_id`);

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
-- AUTO_INCREMENT de tabela `estoque`
--
ALTER TABLE `estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `historico`
--
ALTER TABLE `historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pagamentos_venda`
--
ALTER TABLE `pagamentos_venda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `produtos_codigos_barras`
--
ALTER TABLE `produtos_codigos_barras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para tabelas despejadas
--

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
-- Restrições para tabelas `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD CONSTRAINT `venda_itens_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
