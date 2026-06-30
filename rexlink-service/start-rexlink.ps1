$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '..'))

Write-Host 'Starting RexLink API...'
Write-Host 'Keep this window open while testing RexLink pairing and claims.'
Write-Host ''

node rexlink-service/server.js

Write-Host ''
Write-Host 'RexLink API stopped.'
Read-Host 'Press Enter to close'
