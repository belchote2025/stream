# Script para hacer push a GitHub con reintentos automáticos
# Uso: .\push-github.ps1

Write-Host "🚀 Intentando hacer push a GitHub..." -ForegroundColor Cyan

$maxRetries = 3
$retryCount = 0
$success = $false

while ($retryCount -lt $maxRetries -and -not $success) {
    $retryCount++
    Write-Host "`n📤 Intento $retryCount de $maxRetries..." -ForegroundColor Yellow
    
    $result = git push origin main 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Push exitoso!" -ForegroundColor Green
        $success = $true
    } else {
        Write-Host "❌ Error en el intento $retryCount" -ForegroundColor Red
        Write-Host $result
        
        if ($retryCount -lt $maxRetries) {
            $waitTime = $retryCount * 5
            Write-Host "⏳ Esperando $waitTime segundos antes del siguiente intento..." -ForegroundColor Yellow
            Start-Sleep -Seconds $waitTime
        }
    }
}

if (-not $success) {
    Write-Host "`n❌ No se pudo hacer push después de $maxRetries intentos" -ForegroundColor Red
    Write-Host "`n💡 Alternativas:" -ForegroundColor Cyan
    Write-Host "1. Verificar tu conexión a internet"
    Write-Host "2. Intentar más tarde (GitHub puede estar sobrecargado)"
    Write-Host "3. Usar SSH en lugar de HTTPS:"
    Write-Host "   git remote set-url origin git@github.com:belchote2025/stream.git"
    Write-Host "4. Hacer push de commits individuales:"
    Write-Host "   git push origin <commit-hash>:main"
    exit 1
} else {
    Write-Host "`n✅ ¡Todo listo! Los cambios están en GitHub." -ForegroundColor Green
    exit 0
}





