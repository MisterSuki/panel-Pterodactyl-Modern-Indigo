@echo off
echo ========================================
echo  Resolution des conflits Git
echo ========================================
echo.

cd /d "%~dp0"

echo [1/4] Conservation de ta version locale...
git checkout --ours app/Http/Controllers/Api/Client/ShopController.php
git checkout --ours app/Services/Shop/ResourceBillingService.php
git checkout --ours app/Services/Shop/ServerProvisioner.php
git checkout --ours resources/lang/fr.json
git checkout --ours resources/scripts/api/shop.ts
git checkout --ours resources/scripts/components/dashboard/DashboardContainer.tsx
git checkout --ours resources/scripts/components/dashboard/ServerRow.tsx
git checkout --ours resources/scripts/components/shop/ShopContainer.tsx

echo.
echo [2/4] Ajout des fichiers resolus...
git add .

echo.
echo [3/4] Creation du commit de fusion...
git commit -m "Merge remote main + conservation des modifications locales"

echo.
echo [4/4] Envoi vers GitHub...
git push -u origin main

echo.
echo ========================================
echo  Termine !
echo ========================================
pause