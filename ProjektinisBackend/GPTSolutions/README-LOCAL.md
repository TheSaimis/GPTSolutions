# Backend paleidimas be Docker (terminalas)

Projektas paleidžiamas per **PHP** ir **Composer**, be Docker.

## Ko reikia

1. **PHP 8.2 arba naujesnė**  
   - Atsisiųskite: https://windows.php.net/download/  
   - Arba naudokite **XAMPP**: https://www.apachefriends.org/  
   - Įsitikinkite, kad `php` įeina į sistemos PATH.

2. **Composer**  
   - Atsisiųskite: https://getcomposer.org/download/  
   - Po įdiegimo atidarykite naują terminalą.

3. **MySQL** (arba MariaDB)  
   - Duomenų bazė: `GPT-solutions`  
   - Prijungimas nustatytas faile `.env`:  
     `DATABASE_URL="mysql://root:root@localhost:3306/GPT-solutions"`  
   - Jei naudojate kitą slaptažodį/portą – pakeiskite `.env`.

## Kaip paleisti

**Variantas 1 – PowerShell:**  
Atidarykite terminalą projekto aplanke ir vykdykite:

```powershell
.\run-local.ps1
```

**Variantas 2 – Batch:**  
Dukart paspauskite ant `run-local.bat` arba terminale:

```cmd
run-local.bat
```

Skriptas:

- patikrina, ar įdiegti PHP ir Composer  
- vykdo `composer install`, jei dar neįvykdyta  
- sugeneruoja JWT raktus, jei jų nėra  
- paleidžia PHP įtaisytą serverį adresu **http://127.0.0.1:8000**

## Pirmas paleidimas (MySQL)

Prieš pirmą paleidimą sukurkite MySQL duomenų bazę:

```sql
CREATE DATABASE `GPT-solutions` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Tada galite paleisti migracijas (neprivaloma, jei dar nereikia lentelių):

```powershell
php bin/console doctrine:migrations:migrate --no-interaction
```

## Jei kažko trūksta

- **PHP nerandamas** – įdiekite PHP ir įtraukite į PATH arba įdiekite XAMPP.  
- **Composer nerandamas** – įdiekite Composer ir atidarykite naują terminalą.  
- **JWT klaida** – įdiekite OpenSSL arba sugeneruokite raktus ranka (instrukcijos rodomos skripte).
