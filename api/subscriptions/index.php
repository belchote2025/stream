<?php
/**
 * API para gestionar suscripciones, planes y pagos
 *
 * Endpoints principales:
 *  GET    /api/subscriptions/index.php                  -> Resumen general
 *  GET    /api/subscriptions/index.php?id=1             -> Obtener suscripción específica
 *  POST   /api/subscriptions/index.php                  -> Crear/activar suscripción
 *  PUT    /api/subscriptions/index.php?id=1             -> Actualizar suscripción
 *  DELETE /api/subscriptions/index.php?id=1             -> Cancelar suscripción
 *
 *  GET    /api/subscriptions/index.php?resource=plans   -> Listar planes
 *  POST   /api/subscriptions/index.php?resource=plans   -> Crear plan
 *  PUT    /api/subscriptions/index.php?resource=plans&id=1    -> Actualizar plan
 *  DELETE /api/subscriptions/index.php?resource=plans&id=1    -> Desactivar plan
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
requireAdmin();

$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'subscriptions';

try {
    ensureSubscriptionSchema($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo preparar la base de datos de suscripciones: ' . $e->getMessage()
    ]);
    exit;
}

try {
    if ($resource === 'plans') {
        handlePlanRequests($db, $method);
        exit;
    }

    switch ($method) {
        case 'GET':
            $subscriptionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if ($subscriptionId) {
                getSubscription($db, $subscriptionId);
            } else {
                getSubscriptionsOverview($db);
            }
            break;

        case 'POST':
            createSubscription($db);
            break;

        case 'PUT':
            $subscriptionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$subscriptionId) {
                throw new InvalidArgumentException('Se requiere un ID de suscripción para actualizar.');
            }
            updateSubscription($db, $subscriptionId);
            break;

        case 'DELETE':
            $subscriptionId = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$subscriptionId) {
                throw new InvalidArgumentException('Se requiere un ID de suscripción para cancelar.');
            }
            cancelSubscription($db, $subscriptionId);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido'
            ]);
            break;
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en la API de suscripciones: ' . $e->getMessage()
    ]);
}

/**
 * Asegura que las tablas necesarias existan
 */
function ensureSubscriptionSchema(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
            video_quality VARCHAR(20) DEFAULT 'HD',
            max_screens INT DEFAULT 1,
            download_limit INT DEFAULT 0,
            ads_enabled TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_id INT NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            status ENUM('active','cancelled','expired','pending') NOT NULL DEFAULT 'active',
            billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
            auto_renew TINYINT(1) NOT NULL DEFAULT 1,
            cancelled_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_user_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_id INT NOT NULL,
            subscription_id INT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(5) NOT NULL DEFAULT 'USD',
            payment_method VARCHAR(50) DEFAULT 'card',
            reference VARCHAR(100) NULL,
            status ENUM('paid','pending','failed','refunded') NOT NULL DEFAULT 'paid',
            payment_date DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_payments_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
            CONSTRAINT fk_payments_subscription FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    seedDefaultPlans($db);
}

/**
 * Inserta planes por defecto si no existen
 */
function seedDefaultPlans(PDO $db): void
{
    $count = (int)$db->query("SELECT COUNT(*) FROM plans")->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO plans (name, description, price, billing_cycle, video_quality, max_screens, download_limit, ads_enabled)
        VALUES 
            ('Básico', 'Acceso a contenido estándar con anuncios', 0.00, 'monthly', 'HD', 1, 0, 1),
            ('Premium', 'Todo el contenido en Full HD sin anuncios', 9.99, 'monthly', 'Full HD', 4, 4, 0),
            ('Familiar', 'Hasta 6 perfiles, 4K y descargas ilimitadas', 14.99, 'monthly', '4K', 6, 10, 0)
    ");
    $stmt->execute();
}

/**
 * Maneja solicitudes relacionadas con planes
 */
function handlePlanRequests(PDO $db, string $method): void
{
    switch ($method) {
        case 'GET':
            $plans = getPlans($db);
            echo json_encode([
                'success' => true,
                'data' => $plans
            ]);
            break;

        case 'POST':
            $data = getJsonInput();
            if (empty($data['name']) || !isset($data['price'])) {
                throw new InvalidArgumentException('Nombre y precio del plan son obligatorios.');
            }

            $stmt = $db->prepare("
                INSERT INTO plans (name, description, price, billing_cycle, video_quality, max_screens, download_limit, ads_enabled, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['price'],
                $data['billing_cycle'] ?? 'monthly',
                $data['video_quality'] ?? 'HD',
                $data['max_screens'] ?? 1,
                $data['download_limit'] ?? 0,
                isset($data['ads_enabled']) ? (int)(bool)$data['ads_enabled'] : 0
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Plan creado correctamente',
                'data' => ['id' => (int)$db->lastInsertId()]
            ]);
            break;

        case 'PUT':
            $planId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$planId) {
                throw new InvalidArgumentException('Se requiere el ID del plan.');
            }

            $data = getJsonInput();
            $fields = [];
            $params = [];
            $allowed = ['name','description','price','billing_cycle','video_quality','max_screens','download_limit','ads_enabled','is_active'];

            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = in_array($field, ['ads_enabled','is_active'], true)
                        ? (int)(bool)$data[$field]
                        : $data[$field];
                }
            }

            if (empty($fields)) {
                throw new InvalidArgumentException('No se proporcionaron datos para actualizar.');
            }

            $params[] = $planId;
            $stmt = $db->prepare("UPDATE plans SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?");
            $stmt->execute($params);

            echo json_encode([
                'success' => true,
                'message' => 'Plan actualizado correctamente'
            ]);
            break;

        case 'DELETE':
            $planId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$planId) {
                throw new InvalidArgumentException('Se requiere el ID del plan.');
            }

            // Verificar suscripciones activas
            $stmt = $db->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE plan_id = ? AND status = 'active'");
            $stmt->execute([$planId]);
            if ($stmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('No puedes eliminar un plan con suscripciones activas. Cancela o cambia esas suscripciones primero.');
            }

            $stmt = $db->prepare("UPDATE plans SET is_active = 0 WHERE id = ?");
            $stmt->execute([$planId]);

            echo json_encode([
                'success' => true,
                'message' => 'Plan desactivado correctamente'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido para planes'
            ]);
            break;
    }
}

/**
 * Devuelve la vista/resumen principal
 */
function getSubscriptionsOverview(PDO $db): void
{
    $stats = [
        'totalSubscribers' => 0,
        'activeSubscriptions' => 0,
        'cancelledThisMonth' => 0,
        'monthlyRecurringRevenue' => 0,
        'upcomingRenewals' => 0,
        'pendingPayments' => 0,
        'subscriberGrowth' => 0
    ];

    $stats['totalSubscribers'] = (int)$db->query("SELECT COUNT(*) FROM user_subscriptions")->fetchColumn();
    $stats['activeSubscriptions'] = (int)$db->query("SELECT COUNT(*) FROM user_subscriptions WHERE status = 'active'")->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM user_subscriptions 
        WHERE status = 'cancelled' 
          AND YEAR(updated_at) = YEAR(CURDATE()) 
          AND MONTH(updated_at) = MONTH(CURDATE())
    ");
    $stmt->execute();
    $stats['cancelledThisMonth'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT IFNULL(SUM(p.price), 0) AS mrr
        FROM user_subscriptions us
        JOIN plans p ON us.plan_id = p.id
        WHERE us.status = 'active'
          AND us.billing_cycle = 'monthly'
    ");
    $stmt->execute();
    $stats['monthlyRecurringRevenue'] = (float)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM user_subscriptions 
        WHERE status = 'active' 
          AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $stats['upcomingRenewals'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE status IN ('pending','failed')");
    $stmt->execute();
    $stats['pendingPayments'] = (int)$stmt->fetchColumn();

    $plans = getPlans($db);
    $subscriptions = getSubscriptions($db);
    $payments = getPayments($db);

    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'plans' => $plans,
            'subscriptions' => $subscriptions,
            'payments' => $payments
        ]
    ]);
}

/**
 * Devuelve una suscripción específica
 */
function getSubscription(PDO $db, int $subscriptionId): void
{
    $stmt = $db->prepare("
        SELECT 
            us.*, 
            u.username, 
            u.email, 
            u.full_name, 
            u.avatar_url,
            p.name AS plan_name,
            p.price AS plan_price,
            p.video_quality,
            p.max_screens
        FROM user_subscriptions us
        JOIN users u ON us.user_id = u.id
        JOIN plans p ON us.plan_id = p.id
        WHERE us.id = ?
    ");
    $stmt->execute([$subscriptionId]);
    $subscription = $stmt->fetch();

    if (!$subscription) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Suscripción no encontrada'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => $subscription
    ]);
}

/**
 * Crea una nueva suscripción
 */
function createSubscription(PDO $db): void
{
    $data = getJsonInput();

    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $planId = isset($data['plan_id']) ? (int)$data['plan_id'] : 0;
    $billingCycle = $data['billing_cycle'] ?? 'monthly';
    $status = $data['status'] ?? 'active';
    $autoRenew = isset($data['auto_renew']) ? (int)(bool)$data['auto_renew'] : 1;

    if (!$userId || !$planId) {
        throw new InvalidArgumentException('Usuario y plan son obligatorios.');
    }

    $plan = getPlanById($db, $planId);
    if (!$plan) {
        throw new InvalidArgumentException('El plan seleccionado no existe.');
    }

    $userExists = recordExists($db, 'users', $userId);
    if (!$userExists) {
        throw new InvalidArgumentException('El usuario seleccionado no existe.');
    }

    $startDate = isset($data['start_date']) ? new DateTime($data['start_date']) : new DateTime();
    $endDate = clone $startDate;
    if ($billingCycle === 'yearly') {
        $endDate->modify('+1 year');
    } else {
        $endDate->modify('+1 month');
    }

    $stmt = $db->prepare("
        INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status, billing_cycle, auto_renew)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $planId,
        $startDate->format('Y-m-d H:i:s'),
        $endDate->format('Y-m-d H:i:s'),
        $status,
        $billingCycle,
        $autoRenew
    ]);

    $subscriptionId = (int)$db->lastInsertId();

    if (!empty($data['create_payment'])) {
        $amount = isset($data['amount']) ? (float)$data['amount'] : (float)$plan['price'];
        registerPayment($db, [
            'user_id' => $userId,
            'plan_id' => $planId,
            'subscription_id' => $subscriptionId,
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? 'manual',
            'status' => $data['payment_status'] ?? 'paid'
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Suscripción creada correctamente',
        'data' => ['id' => $subscriptionId]
    ]);
}

/**
 * Actualiza una suscripción existente
 */
function updateSubscription(PDO $db, int $subscriptionId): void
{
    if (!recordExists($db, 'user_subscriptions', $subscriptionId)) {
        throw new InvalidArgumentException('La suscripción no existe.');
    }

    $data = getJsonInput();
    $fields = [];
    $params = [];
    $allowed = ['plan_id','status','billing_cycle','auto_renew','start_date','end_date'];

    foreach ($allowed as $field) {
        if (!isset($data[$field])) {
            continue;
        }

        if ($field === 'plan_id') {
            $plan = getPlanById($db, (int)$data[$field]);
            if (!$plan) {
                throw new InvalidArgumentException('El plan seleccionado no existe.');
            }
            $fields[] = 'plan_id = ?';
            $params[] = (int)$data[$field];
        } elseif (in_array($field, ['start_date','end_date'], true)) {
            $fields[] = "$field = ?";
            $params[] = (new DateTime($data[$field]))->format('Y-m-d H:i:s');
        } elseif ($field === 'auto_renew') {
            $fields[] = "$field = ?";
            $params[] = (int)(bool)$data[$field];
        } else {
            $fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($fields)) {
        throw new InvalidArgumentException('No se proporcionaron datos para actualizar.');
    }

    $fields[] = "updated_at = NOW()";
    $params[] = $subscriptionId;

    $stmt = $db->prepare("UPDATE user_subscriptions SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Suscripción actualizada correctamente'
    ]);
}

/**
 * Cancela una suscripción (DELETE lógico)
 */
function cancelSubscription(PDO $db, int $subscriptionId): void
{
    if (!recordExists($db, 'user_subscriptions', $subscriptionId)) {
        throw new InvalidArgumentException('La suscripción no existe.');
    }

    $stmt = $db->prepare("
        UPDATE user_subscriptions
        SET status = 'cancelled',
            auto_renew = 0,
            cancelled_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$subscriptionId]);

    echo json_encode([
        'success' => true,
        'message' => 'Suscripción cancelada correctamente'
    ]);
}

/**
 * Obtiene la lista de planes con métricas
 */
function getPlans(PDO $db): array
{
    $stmt = $db->query("
        SELECT 
            p.*,
            (
                SELECT COUNT(*) 
                FROM user_subscriptions us 
                WHERE us.plan_id = p.id AND us.status = 'active'
            ) AS subscriber_count
        FROM plans p
        ORDER BY price ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene la lista de suscripciones
 */
function getSubscriptions(PDO $db): array
{
    $stmt = $db->query("
        SELECT 
            us.id,
            us.user_id,
            us.plan_id,
            us.start_date,
            us.end_date,
            us.status,
            us.billing_cycle,
            us.auto_renew,
            us.created_at,
            us.updated_at,
            u.username,
            u.full_name,
            u.email,
            u.avatar_url,
            p.name AS plan_name,
            p.price AS plan_price
        FROM user_subscriptions us
        JOIN users u ON us.user_id = u.id
        JOIN plans p ON us.plan_id = p.id
        ORDER BY us.created_at DESC
    ");

    $subscriptions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['next_payment_date'] = $row['end_date'];
        $subscriptions[] = $row;
    }

    return $subscriptions;
}

/**
 * Obtiene los pagos recientes
 */
function getPayments(PDO $db): array
{
    $stmt = $db->query("
        SELECT 
            pay.id,
            pay.user_id,
            pay.plan_id,
            pay.subscription_id,
            pay.amount,
            pay.currency,
            pay.payment_method,
            pay.reference,
            pay.status,
            pay.payment_date,
            u.username,
            u.full_name,
            p.name AS plan_name
        FROM payments pay
        LEFT JOIN users u ON pay.user_id = u.id
        LEFT JOIN plans p ON pay.plan_id = p.id
        ORDER BY pay.payment_date DESC
        LIMIT 50
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Registra un pago
 */
function registerPayment(PDO $db, array $payload): void
{
    $stmt = $db->prepare("
        INSERT INTO payments (user_id, plan_id, subscription_id, amount, currency, payment_method, reference, status, payment_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $payload['user_id'],
        $payload['plan_id'],
        $payload['subscription_id'] ?? null,
        $payload['amount'],
        $payload['currency'] ?? 'USD',
        $payload['payment_method'] ?? 'card',
        $payload['reference'] ?? null,
        $payload['status'] ?? 'paid'
    ]);
}

/**
 * Obtiene datos de entrada JSON
 */
function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    if (!$input) {
        return $_POST ?? [];
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('JSON inválido: ' . json_last_error_msg());
    }

    return $data ?? [];
}

/**
 * Verifica si un registro existe en una tabla
 */
function recordExists(PDO $db, string $table, int $id): bool
{
    $stmt = $db->prepare("SELECT 1 FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Obtiene un plan por ID
 */
function getPlanById(PDO $db, int $planId): ?array
{
    $stmt = $db->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    return $plan ?: null;
}

