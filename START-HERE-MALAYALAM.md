# പുതിയ update: 25 rides + ESP32 camera

**Rides & attractions → Set up 25 camera rides**ൽ ഓരോ ride-ന്റെ name/points നൽകി unique reader firmware ZIP download ചെയ്യാം. Bill QR scan ചെയ്യുമ്പോൾ assigned ride-ന്റെ points server databaseൽ നിന്ന് കുറയും. Camera code **AI-Thinker ESP32-CAM / OV2640 / PSRAM** profile ആണ്. ആദ്യം `docs/ESP32-CAMERA-MALAYALAM.md` വായിക്കുക. താഴെയുള്ള database login / staff app നിർദ്ദേശങ്ങളും തുടരും.

# Database login — update ചെയ്യേണ്ട വിധം

ഈ update-ൽ **Admin login → Create employee → Staff login → Punch In / Punch Out → Admin attendance register** എല്ലാം ERP project-ന്റെ ഒരേ configured database ഉപയോഗിക്കുന്നു. Android app **Ageless Staff 1.1.0** ആണ്. ആദ്യം ERP update ചെയ്യുക; പിന്നീട് APK install/update ചെയ്യുക.

## 1. നിലവിലെ ERP update ചെയ്യുക

1. നിലവിലെ server project, `.env`, database എന്നിവയുടെ backup സൂക്ഷിക്കുക.
2. ZIPയിലെ updated source files നിലവിലെ project-ിലേക്ക് നൽകുക. **നിങ്ങളുടെ live `.env`, `APP_KEY`, database file/data, uploaded files എന്നിവ നിലനിർത്തണം.** മറ്റൊരു server-ലെ database ഈ ZIPയിലെ SQLite file കൊണ്ട് overwrite ചെയ്യരുത്.
3. PHP 8.2+ ഉപയോഗിച്ച് project folder-ൽ താഴെയുള്ള commands run ചെയ്യുക:

```bash
php artisan config:clear
php artisan migrate --force
php artisan park:database-check
```

Windows-ൽ `03-database-login-check.bat` run ചെയ്താലും connectionയും admin login emailഉം പരിശോധിക്കാം. ഇത് records/password മാറ്റില്ല. `migrate --force` missing schema migrations മാത്രം apply ചെയ്യുന്നു; existing users reset ചെയ്യില്ല.

നൽകിയ project-ന്റെ `.env` **SQLite** ആണ് തിരഞ്ഞെടുക്കുന്നത്. അതിലുള്ള `database/database.sqlite`യും account password hashesഉം ഈ update-ൽ നിലനിർത്തിയിട്ടുണ്ട്. നിങ്ങളുടെ live installation MySQL ആണെങ്കിൽ അതിലെ നിലവിലുള്ള `.env` തന്നെയാണ് ഉപയോഗിക്കേണ്ടത്.

MySQL ഉപയോഗിക്കുന്ന server-ൽ `.env`യിൽ ശരിയായ settings ഉണ്ടായിരിക്കണം:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_existing_project_database
DB_USERNAME=your_server_database_user
DB_PASSWORD=your_server_database_password
```

ഇവ ഉദാഹരണ placeholder values ആണ്; നിങ്ങളുടെ existing database details നൽകണം. PHPയിൽ MySQL PDO extension / database access വേണം. `.env` മാറ്റിയ ശേഷം `php artisan config:clear` ചെയ്യുക. SQLiteയും MySQLയും തമ്മിൽ ഈ update automatic data migration നടത്തില്ല. Existing users ഉള്ള database തന്നെയാണ് തിരഞ്ഞെടുക്കേണ്ടത്.

## 2. Admin login ശരിയാക്കുക

**Login screen-ൽ നൽകേണ്ടത് `users` table-ലെ admin emailയും application passwordഉം ആണ്.** MySQL `root`, phpMyAdmin login അല്ലെങ്കിൽ `.env`യിലെ `DB_USERNAME / DB_PASSWORD` ഉപയോഗിച്ച് application login ചെയ്യാനാവില്ല.

`php artisan park:database-check` നിലവിലുള്ള admin login email കാണിക്കും. Password അറിയില്ലെങ്കിൽ trusted server console-ൽ:

```bash
php artisan park:admin-password
```

Admin email തിരഞ്ഞെടുക്കുക; പുതിയ password രണ്ടുതവണ നൽകുക. Minimum 12 characters, uppercase, lowercase, number വേണം. Windows-ൽ `04-reset-admin-password.bat` ഉപയോഗിക്കാം. Password database-ൽ hash ആയി save ആകും; പഴയ sessions അടുത്ത authenticated request-ൽ sign out ആകും. പഴയ password plaintext ആയി തിരിച്ചെടുക്കാനാവില്ല; default password ചേർത്തിട്ടില്ല.

Admin account disabled ആണെങ്കിൽ അത് വ്യക്തമായി restore ചെയ്യാൻ:

```bash
php artisan park:admin-password --activate
```

Windows: `04-reset-admin-password.bat --activate`.

Admin account ഇല്ലെന്ന് check കാണിച്ചാൽ മാത്രം:

```bash
php artisan park:install
```

ഇത് നിലവിലുള്ള staff records നിലനിർത്തി ആദ്യ admin സൃഷ്ടിക്കും. Existing admin ഉണ്ടെങ്കിൽ account/password മാറ്റില്ല. `migrate:fresh`, `db:wipe`, database file delete എന്നിവ ഉപയോഗിക്കരുത്.

## 3. Android app & user creation

1. പുതിയ `Ageless_Staff.apk` install/update ചെയ്യുക. പഴയ APKയുടെ അതേ signing identity ആയതിനാൽ compatible update ആണ്; Android 8+ വേണം.
2. App തുറന്ന് **ERP server address** നൽകുക. Hosted server: valid HTTPS address. Local PC: അതിന്റെ LAN IPയും portഉം, ഉദാ. `http://192.168.1.10:8000`; **Local Wi-Fi HTTP** tick ചെയ്യുക. PCയും ഫോണും same Wi-Fiയിൽ ആയിരിക്കണം. Appൽ `localhost` നൽകരുത്.
3. Databaseയിലെ active **admin email / application password** ഉപയോഗിച്ച് login ചെയ്യുക. **Team management** Home തുറക്കും.
4. **Add employee** തുറന്ന് name, phone, trade, unique email, password, **Staff** role, active status, assigned work site, attendance enable എന്നിവ നൽകി **Create Employee Profile** അമർത്തുക. Account central database-ൽ save ആകും.
5. Employee സ്വന്തം ഫോണിൽ അതേ ERP addressയും നിങ്ങൾ നൽകിയ email/passwordഉം ഉപയോഗിച്ച് login ചെയ്യുക. Staffയ്ക്ക് My day / History / Requests / Profile തുറക്കും.
6. **Punch In / Punch Out** അമർത്തുമ്പോൾ fresh phone location ആവശ്യപ്പെടും. GPS ON ആക്കുക; precise location permission അനുവദിക്കുക. Server confirmation വന്നാൽ record database-ൽ save ആയി.
7. Admin Home → **Attendance register**ൽ അതേ records കാണാം. **Manage employees**ൽ password/status update ചെയ്യാം. **Work sites**ൽ യഥാർത്ഥ centre/radius/accuracy configure ചെയ്യുക.

Appൽ database server username/password നൽകേണ്ടതില്ല. Login/users/attendance എല്ലാം authenticated ERP routes വഴിയാണ് ഒരേ database-ൽ എത്തുന്നത്. Staffയ്ക്ക് admin user creation അനുവദിക്കില്ല. App offline punches queue ചെയ്യില്ല; unconfirmed punch ഉണ്ടെങ്കിൽ **History** പരിശോധിച്ച് ആവശ്യമെങ്കിൽ **Retry the same punch** ഉപയോഗിക്കുക.

## Login അല്ലെങ്കിൽ connection ഇപ്പോഴും പരാജയപ്പെട്ടാൽ

| കാണുന്ന പ്രശ്നം | പരിശോധിക്കേണ്ടത് |
| --- | --- |
| Email/password incorrect | Diagnosticൽ കാണുന്ന admin email; application password; active account. ആവശ്യമെങ്കിൽ console password reset. |
| Database connection check fails | Server `.env`, correct existing database, PDO extension, DB service/credentials, file permissions. |
| Loginക്ക് മുമ്പ് HTTP 500 | ERP log server-ൽ പരിശോധിക്കുക; DB connection, APP_KEY, pending migrations, writable storage. |
| HTTP 404 / mobile route missing | Updated ERP source server-ൽ നൽകുക; cached routes ഉണ്ടെങ്കിൽ `php artisan route:clear`. |
| Phone cannot connect | ERP running status, IP/port, same Wi-Fi, private-network firewall, valid HTTPS certificate. |
| No admin in selected database | Existing correct database തിരഞ്ഞെടുത്തോ എന്ന് പരിശോധിക്കുക; ശരിയായ databaseൽ മാത്രമേ `park:install` run ചെയ്യാവൂ. |
| GPS / radius error | Phone precise location permission, fresh GPS, assigned site centre/radius/accuracy. |

Backend, real login/user creation/punch database flow, APK signature എന്നിവ പരിശോധിച്ചു. നിങ്ങളുടെ live server connectionയും physical phone GPSഉം സ്ഥലത്ത് ഒരു staff account ഉപയോഗിച്ച് ആദ്യ ഉപയോഗത്തിന് മുമ്പ് പരിശോധിക്കണം. വിശദമായ results: `docs/DATABASE-LOGIN-TEST-REPORT.md`.
