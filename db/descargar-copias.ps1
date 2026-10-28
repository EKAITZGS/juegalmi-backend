# Descarga a este PC las copias de seguridad de la base de datos (copia fuera de AWS).
# Requiere el alias jokoa-symfony de ~/.ssh/config y el lab encendido. Uso: powershell -File db\descargar-copias.ps1
param([string]$Destino = "$env:USERPROFILE\Documents\JuegAlmi-copias")
New-Item -ItemType Directory -Force $Destino | Out-Null
scp -q "jokoa-symfony:juegalmi/copias/juegalmi-*.sql.gz" $Destino
if ($LASTEXITCODE -ne 0) { throw "No se pudieron descargar las copias (¿lab apagado o IP del bastión cambiada?)" }
Get-ChildItem $Destino -Filter *.sql.gz | Sort-Object LastWriteTime -Descending | Select-Object -First 5 Name, Length
