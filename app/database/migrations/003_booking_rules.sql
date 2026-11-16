-- Frozen booking duration and full timestamp overlap validation.
DROP TRIGGER IF EXISTS trigger_validar_agendamento_insert;
DROP TRIGGER IF EXISTS trigger_validar_agendamento_update;
DROP PROCEDURE IF EXISTS finalizar_agendamentos;

DELIMITER //

CREATE PROCEDURE `finalizar_agendamentos`()
BEGIN
  UPDATE agendamentos a
  SET a.status = 'CONCLUIDO'
  WHERE a.status = 'CONFIRMADO'
    AND DATE_ADD(TIMESTAMP(a.data, a.hora_inicio), INTERVAL a.duracao_minutos MINUTE) < CURRENT_TIMESTAMP;
END//

CREATE TRIGGER `trigger_validar_agendamento_insert`
BEFORE INSERT ON `agendamentos`
FOR EACH ROW
BEGIN
  DECLARE v_exige_cenario TINYINT DEFAULT 0;
  DECLARE v_tipo_nicho_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_cenario_nicho_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_cenario_mes INT DEFAULT NULL;
  DECLARE v_cenario_ano INT DEFAULT NULL;

  IF NEW.status = 'CONFIRMADO' THEN
    IF NEW.data < CURRENT_DATE + INTERVAL 7 DAY THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Necessário 7 dias de antecedência';
    END IF;
    IF NEW.hora_inicio < '06:00:00' OR NEW.hora_inicio >= '20:00:00' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O horário inicial deve estar entre 06:00 e 20:00';
    END IF;
    IF NEW.duracao_minutos IS NULL OR NEW.duracao_minutos <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duração contratada inválida';
    END IF;
    SELECT exige_cenario, nicho_id INTO v_exige_cenario, v_tipo_nicho_id
      FROM tipos_servico WHERE id = NEW.tipo_servico_id LIMIT 1;
    IF v_exige_cenario = 1 AND NEW.cenario_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este tipo de serviço exige cenário';
    END IF;
    IF NEW.cenario_id IS NOT NULL THEN
      SELECT nicho_id, mes, ano INTO v_cenario_nicho_id, v_cenario_mes, v_cenario_ano
        FROM cenarios WHERE id = NEW.cenario_id LIMIT 1;
      IF v_cenario_nicho_id <> v_tipo_nicho_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cenário não pertence ao mesmo nicho do serviço';
      END IF;
      IF v_cenario_mes IS NOT NULL AND v_cenario_mes <> MONTH(NEW.data) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cenário indisponível para o mês escolhido';
      END IF;
      IF v_cenario_ano IS NOT NULL AND v_cenario_ano <> YEAR(NEW.data) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cenário indisponível para o ano escolhido';
      END IF;
    END IF;
    IF EXISTS (
      SELECT 1 FROM agendamentos a
      WHERE a.fotografo_id = NEW.fotografo_id
        AND a.status = 'CONFIRMADO'
        AND TIMESTAMP(NEW.data, NEW.hora_inicio) < DATE_ADD(TIMESTAMP(a.data, a.hora_inicio), INTERVAL a.duracao_minutos MINUTE)
        AND DATE_ADD(TIMESTAMP(NEW.data, NEW.hora_inicio), INTERVAL NEW.duracao_minutos MINUTE) > TIMESTAMP(a.data, a.hora_inicio)
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflito de horário com outro atendimento já confirmado para este fotógrafo';
    END IF;
  END IF;
END//

CREATE TRIGGER `trigger_validar_agendamento_update`
BEFORE UPDATE ON `agendamentos`
FOR EACH ROW
BEGIN
  DECLARE v_exige_cenario TINYINT DEFAULT 0;
  DECLARE v_tipo_nicho_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_cenario_nicho_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_cenario_mes INT DEFAULT NULL;
  DECLARE v_cenario_ano INT DEFAULT NULL;

  IF NEW.status = 'CONFIRMADO' THEN
    IF (OLD.status <> 'CONFIRMADO' OR NEW.data <> OLD.data OR NEW.hora_inicio <> OLD.hora_inicio OR NEW.fotografo_id <> OLD.fotografo_id OR NEW.tipo_servico_id <> OLD.tipo_servico_id) AND NEW.data < CURRENT_DATE + INTERVAL 7 DAY THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Necessário 7 dias de antecedência';
    END IF;
    IF NEW.hora_inicio < '06:00:00' OR NEW.hora_inicio >= '20:00:00' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O horário inicial deve estar entre 06:00 e 20:00';
    END IF;
    IF NEW.duracao_minutos IS NULL OR NEW.duracao_minutos <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duração contratada inválida';
    END IF;
    SELECT exige_cenario, nicho_id INTO v_exige_cenario, v_tipo_nicho_id
      FROM tipos_servico WHERE id = NEW.tipo_servico_id LIMIT 1;
    IF v_exige_cenario = 1 AND NEW.cenario_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este tipo de serviço exige cenário';
    END IF;
    IF NEW.cenario_id IS NOT NULL THEN
      SELECT nicho_id, mes, ano INTO v_cenario_nicho_id, v_cenario_mes, v_cenario_ano
        FROM cenarios WHERE id = NEW.cenario_id LIMIT 1;
      IF v_cenario_nicho_id <> v_tipo_nicho_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cenário não pertence ao mesmo nicho do serviço';
      END IF;
      IF v_cenario_mes IS NOT NULL AND v_cenario_mes <> MONTH(NEW.data) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cenário indisponível para o mês escolhido';
      END IF;
      IF v_cenario_ano IS NOT NULL AND v_cenario_ano <> YEAR(NEW.data) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cenário indisponível para o ano escolhido';
      END IF;
    END IF;
    IF EXISTS (
      SELECT 1 FROM agendamentos a
      WHERE a.fotografo_id = NEW.fotografo_id
        AND a.status = 'CONFIRMADO'
        AND a.id <> OLD.id
        AND TIMESTAMP(NEW.data, NEW.hora_inicio) < DATE_ADD(TIMESTAMP(a.data, a.hora_inicio), INTERVAL a.duracao_minutos MINUTE)
        AND DATE_ADD(TIMESTAMP(NEW.data, NEW.hora_inicio), INTERVAL NEW.duracao_minutos MINUTE) > TIMESTAMP(a.data, a.hora_inicio)
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflito de horário com outro atendimento já confirmado para este fotógrafo';
    END IF;
  END IF;
END//

DELIMITER ;
