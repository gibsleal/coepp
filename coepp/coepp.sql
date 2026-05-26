-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 25/10/2025 às 18:22
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
-- Banco de dados: `coepp`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `agendamentos`
--

CREATE TABLE `agendamentos` (
  `id` int(11) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `estagiario_id` int(11) NOT NULL,
  `data` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `supervisor` varchar(255) DEFAULT NULL,
  `tipo_servico` varchar(255) DEFAULT NULL,
  `obs` varchar(255) DEFAULT NULL,
  `sala` int(11) NOT NULL,
  `inicio` datetime NOT NULL,
  `fim` datetime NOT NULL,
  `status` enum('ativo','cancelado') DEFAULT 'ativo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `estagiarios`
--

CREATE TABLE `estagiarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `matricula` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `semestre` enum('4','5','6','7','8') DEFAULT NULL,
  `supervisor` varchar(100) DEFAULT NULL,
  `tipo_servico` varchar(100) DEFAULT NULL,
  `disponibilidade` text DEFAULT NULL,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pacientes`
--

CREATE TABLE `pacientes` (
  `id` int(11) NOT NULL,
  `numero_prontuario` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `preferencial` tinyint(1) NOT NULL DEFAULT 0,
  `preferencial_obs` varchar(255) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `telefone` varchar(20) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `endereco` varchar(255) NOT NULL,
  `caso_preferencial` enum('Sim','Não') DEFAULT 'Não',
  `estuda_fsa` enum('Sim','Não') DEFAULT 'Não',
  `ra` varchar(20) DEFAULT NULL,
  `encaminhamento` varchar(100) DEFAULT NULL,
  `tipo_servico` varchar(100) DEFAULT NULL,
  `email` varchar(120) NOT NULL,
  `estagiario` varchar(150) DEFAULT NULL,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pacientes_backup`
--

CREATE TABLE `pacientes_backup` (
  `id` int(11) NOT NULL,
  `numero_prontuario` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `preferencial` tinyint(1) NOT NULL DEFAULT 0,
  `preferencial_obs` varchar(255) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `caso_preferencial` enum('Sim','Não') DEFAULT 'Não',
  `estuda_fsa` enum('Sim','Não') DEFAULT 'Não',
  `ra` varchar(20) DEFAULT NULL,
  `encaminhamento` varchar(100) DEFAULT NULL,
  `tipo_servico` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `estagiario` varchar(150) DEFAULT NULL,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pacientes_backup`
--

INSERT INTO `pacientes_backup` (`id`, `numero_prontuario`, `nome`, `preferencial`, `preferencial_obs`, `data_nascimento`, `telefone`, `cpf`, `endereco`, `caso_preferencial`, `estuda_fsa`, `ra`, `encaminhamento`, `tipo_servico`, `email`, `estagiario`, `data_cadastro`) VALUES
(1, 0, 'Gupaciente Teste', 0, NULL, '2015-01-14', '(11)1111-1111', '000.000.000-00', 'Rua dos Multiplos Testes - Bairro Teste - Cidade Teste', 'Sim', 'Não', '', 'Ciência da Computação', 'Triagem', 'teste@fsa.com', NULL, '2025-08-15 23:15:25'),
(3, 1, 'Mathpaciente Teste1', 0, NULL, '2002-01-01', '(77) 7777-7777', '777.777.777-77', 'Rua teste 44 - Nova Teste', 'Não', 'Não', '', 'Espontânea', 'Triagem', 'Mathpaciente@fsa.como', NULL, '2025-08-29 15:06:26'),
(4, 2, 'Paciteste1', 0, NULL, '2000-02-01', '(22) 3344-5556', '243.546.576-76', 'Paciteste1 - teste 1 -', 'Sim', 'Sim', '445566', 'Demanda espontânea', 'Triagem', 'Paciteste1@hotmail.com', NULL, '2025-08-30 17:07:35'),
(5, 3, 'Teste Pref', 0, NULL, '1999-06-03', '(44) 44445-5555', '999.999.999-99', 'Pref Teste', 'Sim', 'Não', NULL, 'UBS', 'Avaliação', 'TestePref@fsa.com', NULL, '2025-09-02 21:52:45');

-- --------------------------------------------------------

--
-- Estrutura para tabela `salas`
--

CREATE TABLE `salas` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `salas`
--

INSERT INTO `salas` (`id`, `nome`) VALUES
(1, 'Sala 1'),
(10, 'Sala 10'),
(11, 'Sala 11'),
(12, 'Sala 12'),
(2, 'Sala 2'),
(3, 'Sala 3'),
(4, 'Sala 4'),
(5, 'Sala 5'),
(6, 'Sala 6'),
(7, 'Sala 7'),
(8, 'Sala 8'),
(9, 'Sala 9');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `senha` varchar(100) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cargo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `telefone`, `cargo`) VALUES
(3, 'Admin', 'admin@coepp.com', 'FSA@#2025', '', 'admin');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_agendamento_paciente` (`paciente_id`),
  ADD KEY `fk_agendamento_estagiario` (`estagiario_id`);

--
-- Índices de tabela `estagiarios`
--
ALTER TABLE `estagiarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula` (`matricula`);

--
-- Índices de tabela `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_prontuario` (`numero_prontuario`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD UNIQUE KEY `uniq_numero_prontuario` (`numero_prontuario`),
  ADD KEY `idx_nome` (`nome`),
  ADD KEY `idx_preferencial` (`preferencial`),
  ADD KEY `idx_cpf` (`cpf`);

--
-- Índices de tabela `pacientes_backup`
--
ALTER TABLE `pacientes_backup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_prontuario` (`numero_prontuario`),
  ADD UNIQUE KEY `cpf` (`cpf`);

--
-- Índices de tabela `salas`
--
ALTER TABLE `salas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `agendamentos`
--
ALTER TABLE `agendamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `estagiarios`
--
ALTER TABLE `estagiarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `pacientes_backup`
--
ALTER TABLE `pacientes_backup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `salas`
--
ALTER TABLE `salas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD CONSTRAINT `fk_agendamento_estagiario` FOREIGN KEY (`estagiario_id`) REFERENCES `estagiarios` (`id`),
  ADD CONSTRAINT `fk_agendamento_paciente` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
