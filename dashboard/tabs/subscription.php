<?php
// Verificar si el usuario está autenticado
if (!$auth->isAuthenticated()) {
    redirect('/login.php');
}

$db = getDbConnection();
$userId = $user['id'];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Obtener información de la suscripción actual
try {
    $stmt = $db->prepare("
        SELECT us.*, p.name as plan_name, p.price, p.billing_cycle,
               p.video_quality, p.max_screens, p.ads_enabled
        FROM user_subscriptions us
        JOIN plans p ON us.plan_id = p.id
        WHERE us.user_id = ? AND us.status = 'active'
        ORDER BY us.start_date DESC
        LIMIT 1
    
    ");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch();
    
    // Obtener el historial de pagos
    $stmt = $db->prepare("
        SELECT p.*, pl.name as plan_name
        FROM payments p
        JOIN plans pl ON p.plan_id = pl.id
        WHERE p.user_id = ?
        ORDER BY p.payment_date DESC
        LIMIT 5
    
    ");
    $stmt->execute([$userId]);
    $payments = $stmt->fetchAll();
    
    // Obtener planes disponibles
    $stmt = $db->prepare("SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC");
    $stmt->execute();
    $plans = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Error al cargar la información de la suscripción';
    error_log($e->getMessage());
}

// Procesar cambio de plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_plan'])) {
    try {
        $planId = (int)$_POST['plan_id'];
        $billingCycle = $_POST['billing_cycle'];
        
        // Validar plan
        $stmt = $db->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmt->execute([$planId]);
        $newPlan = $stmt->fetch();
        
        if (!$newPlan) {
            throw new Exception('Plan no válido');
        }
        
        if ($subscription) {
            // Actualizar plan existente
            $stmt = $db->prepare("
                UPDATE user_subscriptions 
                SET plan_id = ?, billing_cycle = ?, updated_at = NOW()
                WHERE user_id = ? AND status = 'active'
            
            ");
            $stmt->execute([$planId, $billingCycle, $userId]);
        } else {
            // Crear nueva suscripción
            $startDate = date('Y-m-d H:i:s');
            $endDate = $billingCycle === 'yearly' 
                ? date('Y-m-d H:i:s', strtotime('+1 year')) 
                : date('Y-m-d H:i:s', strtotime('+1 month'));
                
            $stmt = $db->prepare("
                INSERT INTO user_subscriptions 
                (user_id, plan_id, start_date, end_date, status, billing_cycle, created_at)
                VALUES (?, ?, ?, ?, 'active', ?, NOW())
            
            ");
            $stmt->execute([$userId, $planId, $startDate, $endDate, $billingCycle]);
        }
        
        $_SESSION['success'] = 'Plan actualizado correctamente';
        redirect('/dashboard?tab=subscription');
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="dashboard-subscription">
    <div class="page-header">
        <h1 class="page-title">Mi Suscripción</h1>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <?php if ($subscription): ?>
                <!-- Tarjeta de suscripción activa -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Plan Actual</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1"><?php echo htmlspecialchars($subscription['plan_name']); ?></h3>
                                <p class="text-muted mb-0">
                                    <?php echo $subscription['billing_cycle'] === 'yearly' ? 'Pago anual' : 'Pago mensual'; ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <div class="h3 mb-0">
                                    $<?php echo number_format($subscription['price'], 2); ?>
                                    <small class="text-muted fs-6">/<?php echo $subscription['billing_cycle'] === 'yearly' ? 'año' : 'mes'; ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Próximo cargo</h6>
                                    <p class="mb-0">
                                        $<?php echo number_format($subscription['price'], 2); ?> 
                                        el <?php echo date('d/m/Y', strtotime($subscription['end_date'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Estado</h6>
                                    <p class="mb-0">
                                        <span class="badge bg-success">Activa</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePlanModal">
                                Cambiar de plan
                            </button>
                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelSubscriptionModal">
                                Cancelar suscripción
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Historial de pagos -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Historial de pagos</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($payments)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Descripción</th>
                                            <th class="text-end">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($payment['plan_name']); ?></td>
                                                <td class="text-end">$<?php echo number_format($payment['amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <p class="text-muted">No hay registros de pagos</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Sin suscripción activa -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-tv fa-4x text-muted mb-3"></i>
                        <h3>No tienes una suscripción activa</h3>
                        <p class="text-muted mb-4">Suscríbete para disfrutar de todo el catálogo</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changePlanModal">
                            Ver planes disponibles
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <!-- Beneficios del plan -->
            <?php if ($subscription): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Beneficios</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i> 
                                Calidad <?php echo ucfirst($subscription['video_quality']); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i> 
                                Hasta <?php echo $subscription['max_screens']; ?> pantallas
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i> 
                                <?php echo $subscription['ads_enabled'] ? 'Sin anuncios' : 'Anuncios ocasionales'; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Soporte -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">¿Necesitas ayuda?</h5>
                </div>
                <div class="card-body">
                    <p>Consulta nuestras preguntas frecuentes o contacta con nuestro equipo de soporte.</p>
                    <a href="/help" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-question-circle me-1"></i> Centro de ayuda
                    </a>
                    <a href="/contact" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-headset me-1"></i> Contactar soporte
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cambiar de plan -->
<div class="modal fade" id="changePlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar de plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if ($subscription): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Tu cambio de plan se aplicará en tu próximo ciclo de facturación.
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Selecciona un plan</label>
                        <div class="list-group">
                            <?php foreach ($plans as $plan): ?>
                                <label class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="plan_id" 
                                               value="<?php echo $plan['id']; ?>"
                                               <?php echo ($subscription && $subscription['plan_id'] == $plan['id']) ? 'checked' : ''; ?>>
                                        <span class="form-check-label w-100">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong><?php echo htmlspecialchars($plan['name']); ?></strong>
                                                <span>
                                                    $<?php echo number_format($plan['price'], 2); ?>
                                                    <small class="text-muted">/mes</small>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Calidad <?php echo ucfirst($plan['video_quality']); ?> · 
                                                <?php echo $plan['max_screens']; ?> pantallas
                                            </small>
                                        </span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ciclo de facturación</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="billing_cycle" 
                                   id="monthly" 
                                   value="monthly"
                                   <?php echo (!$subscription || $subscription['billing_cycle'] === 'monthly') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="monthly">
                                Pago mensual
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="billing_cycle" 
                                   id="yearly" 
                                   value="yearly"
                                   <?php echo ($subscription && $subscription['billing_cycle'] === 'yearly') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="yearly">
                                Pago anual (Ahorra 15%)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="change_plan" class="btn btn-primary">
                        <?php echo $subscription ? 'Cambiar plan' : 'Suscribirse'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para cancelar suscripción -->
<div class="modal fade" id="cancelSubscriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancelar suscripción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h5>¿Estás seguro de que quieres cancelar tu suscripción?</h5>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    Tu suscripción permanecerá activa hasta el final de tu período de facturación actual.
                    Podrás seguir disfrutando de todos los beneficios hasta entonces.
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmCancel">
                    <label class="form-check-label" for="confirmCancel">
                        Entiendo que perderé el acceso a mi suscripción al final del período de facturación.
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mantener mi suscripción</button>
                <button type="button" id="confirmCancelBtn" class="btn btn-danger" disabled>
                    <i class="fas fa-times-circle me-1"></i> Confirmar cancelación
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Habilitar/deshabilitar botón de confirmación de cancelación
    const confirmCancel = document.getElementById('confirmCancel');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    
    if (confirmCancel && confirmCancelBtn) {
        confirmCancel.addEventListener('change', function() {
            confirmCancelBtn.disabled = !this.checked;
        });
        
        confirmCancelBtn.addEventListener('click', function() {
            // Aquí iría la lógica para cancelar la suscripción
            // Por ahora, mostramos un mensaje de éxito
            alert('Tu suscripción se cancelará al final del período de facturación actual.');
            
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('cancelSubscriptionModal'));
            modal.hide();
        });
    }
});
</script>
