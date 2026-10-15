# Bezpieczeństwo

## Hasła i PIN

password_hash / password_verify; Argon2id, gdy dostępny, inaczej PASSWORD_DEFAULT. password_needs_rehash aktualizuje hash przy poprawnym logowaniu. Nowe hasło: minimum 12 znaków, maksimum 72 bajty (wspólna granica zapobiega obcięciu bcrypt), bez bajtu NUL, zgodne potwierdzenie.

PIN jest stałym ciągiem dokładnie sześciu cyfr ASCII, również 000123. W DB: wolny hash z HMAC-SHA256(PIN, APP_KEY). APP_KEY jest poza repozytorium w .env / środowisku, obowiązkowo minimum 32 znaki. Nie rotować go bez planu ponownego ustawienia PIN-ów. PIN-u nie ma w URL, logach, sesji ani odtworzonym HTML. Jedyny jawny PIN w kodzie to fikcyjny input testów.

**Sześć cyfr jest kompromisem UX i bezpieczeństwa.** Nie zapewnia ochrony równoważnej silnemu hasłu ani resetowi przez potwierdzony e-mail i jednorazowy token. Ograniczony grant zmniejsza zakres dostępu przed resetem, ale znajomość PIN-u nadal pozwala ustanowić nowe hasło. Dla publicznego serwisu zalecane jest wdrożenie potwierdzonego kanału e-mail. Nie wdrożono wysyłki wiadomości.

## Limity

W sesji maksymalnie pięć błędów login. Szóste żądanie jest odrzucane przed weryfikacją hasła. Licznik login nie wygasa w trwającej sesji PHP; licznik recovery i akcji wrażliwych ma okno 15 minut. Poprawne próby nie zerują wcześniejszych błędów w oknie.

W bazie auth_limits są dwa niezależne klucze HMAC na kanał: znormalizowany identyfikator konta oraz IP. Login: 5 błędów/konto i 20/IP; recovery: 5/konto i 10/IP; potwierdzenie hasła w ustawieniach: 5/użytkownik i 20/IP. Okno 900 sekund od utworzenia lub odnowienia wygasłego koszyka, niezależne od cookies. Ochrona per konto zapobiega obejściu zmieniając IP; koszyk IP ogranicza próby na wielu kontach.

Baza przechowuje wyłącznie klucz HMAC, licznik i czas wygaśnięcia, bez surowego IP/e-maila. Używany jest REMOTE_ADDR; aplikacja celowo nie ufa przekazywanym przez klienta nagłówkom X-Forwarded-For. Przed wdrożeniem za proxy potrzebna jest jawna konfiguracja zaufanego proxy.

Oba koszyki są blokowane w transakcji w stałej kolejności (SELECT FOR UPDATE / blokada UPSERT), również podczas sprawdzania sekretu. Test sześciu niezależnych procesów potwierdził dokładnie pięć weryfikacji. Blokady mogą być wykorzystane do czasowego utrudnienia logowania znanemu użytkownikowi; jest to ograniczenie przyjętego modelu.

Wygasłe koszyki odnowią się przy kolejnej próbie. Operator może okresowo usuwać stare metadane:
```sql
DELETE FROM auth_limits WHERE expires_at < UNIX_TIMESTAMP() - 86400;
```
Nie kasuje to aktywnych blokad. Masowe ataki/rejestracje wymagają dodatkowych limitów na warstwie serwera WWW.

## Sesje i odzyskiwanie

Cookie: lifetime=0, HttpOnly, SameSite=Lax; Secure przy HTTPS lub APP_ENV=production. strict_mode, tylko cookie, brak session ID w URL. ID rotuje po logowaniu, rejestracji, wejściu w recovery, zmianie hasła/PIN-u i wygaśnięciu zwykłej sesji. Wylogowanie wyłącznie POST+CSRF niszczy dane sesji i cookie.

Timeout: 30 minut bezczynności i 8 godzin maksymalnego czasu. auth_version sprawdzana przy każdym zwykłym żądaniu unieważnia wcześniejsze sesje po zmianie sekretu. Wyłączenie konta także odbiera dostęp. Cookie sesyjne nie gwarantuje wykrycia zamknięcia przeglądarki: przywracanie sesji przez przeglądarkę może je zachować.

Grant recovery ma 5 minut, ID użytkownika i wersję uwierzytelnienia. Nie przechodzi zwykłego logowania. Reset wymaga CSRF i warunkowego UPDATE auth_version; sukces konsumuje dostęp w każdej kopii starego grantu i unieważnia zwykłe sesje. Stare konto z NULL pin_hash nie odzyska dostępu przez pusty PIN.

## Formularze i dostęp

PDO z wyłączoną emulacją przygotowanych zapytań. E-mail jest trim/lowercase, ograniczony do formatu akceptowanego przez FILTER_VALIDATE_EMAIL i 190 bajtów; DB wymusza UNIQUE. Logowanie odrzuca niepoprawny format również przed wyszukaniem w kolacji DB, co zapobiega aliasom diakrytycznym. Naruszenie UNIQUE przy wyścigu rejestracji jest obsłużone bez ujawniania SQL.

Każdy POST ma kryptograficzny token CSRF porównywany hash_equals, w tym login, rejestracja i logout. Każde dynamiczne pole HTML jest escapowane ENT_QUOTES|ENT_SUBSTITUTE/UTF-8. CSP nie dopuszcza skryptów inline ani obcych domen; frame-ancestors none, base-uri none, form-action self. Cache-Control no-store, nosniff, Referrer-Policy same-origin.

Właściciel firmy nie jest globalnym administratorem. company_id w URL jest sprawdzane przez aktywne członkostwo; role nie pochodzą z formularza. Konto i firma są tworzone atomowo, z ROLLBACK w razie błędu. Ochrona dotyczy endpointów, nie tylko linków w menu.

## Konfiguracja i ograniczenia weryfikacji

Udostępniać wyłącznie public/. .env i kod aplikacji są poza DocumentRoot. APP_ENV=production wymaga HTTPS; bez TLS cookie Secure nie będzie wysyłane. Lokalny HTTP służy wyłącznie testom. Konto DB produkcji powinno mieć minimalne uprawnienia DML, odrębne od operatora migracji.

Błędy pokazują neutralne 503, a log zapisuje tylko klasę i kod wyjątku, bez parametrów SQL, haseł, PIN-ów czy sesji. Nie ma zewnętrznych trackerów.

Sprawdzono PHP, MariaDB, SQL, negatywne scenariusze i HTTP. Nie wykonywano audytu penetracyjnego, rzeczywistego HTTPS ani przeglądu wizualnego w przeglądarce. Brak potwierdzania e-maila i mailowego resetu to świadome ograniczenia Etapu 2.
