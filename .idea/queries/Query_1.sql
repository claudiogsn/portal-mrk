CREATE TABLE product_daily_projections (
                                           id INT AUTO_INCREMENT PRIMARY KEY,
                                           system_unit_id INT NOT NULL,
                                           product_codigo INT NOT NULL, -- Agora referenciamos o 'codigo' do produto
                                           day_of_week ENUM('segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo') NOT NULL,
                                           quantity DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
                                           created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                           updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                                           deleted_at DATETIME DEFAULT NULL,

    -- A unicidade agora é Unidade + Codigo do Produto + Dia
                                           UNIQUE KEY unique_projection (system_unit_id, product_codigo, day_of_week)
);

-- Índices para performance
CREATE INDEX idx_proj_unit_code ON product_daily_projections(system_unit_id, product_codigo);