<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$pageTitle = 'Ochrana osobných údajov';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-5">
                    <h1 class="mb-4">Ochrana osobných údajov</h1>
                    <p class="text-muted mb-4">Platné od: 10. júla 2025</p>
                    
                    <h2>1. Úvodné informácie</h2>
                    <p>Láskavý Priestor, s.r.o. ("my", "naša spoločnosť") si váži vašu dôveru a je nám záležať na ochrane vašich osobných údajov. Tento dokument popisuje, ako zbierame, používame a chránime vaše osobné údaje v súlade s Nariadením GDPR.</p>
                    
                    <h2>2. Správca osobných údajov</h2>
                    <div class="bg-light p-3 rounded">
                        <strong>Láskavý Priestor, s.r.o.</strong><br>
                        Adresa: Bratislava, Slovensko<br>
                        Email: info@laskavypriestor.eu<br>
                        Telefón: +421 123 456 789
                    </div>
                    
                    <h2>3. Aké údaje zbierame</h2>
                    <h3>3.1 Údaje pri registrácii</h3>
                    <ul>
                        <li>Meno a priezvisko</li>
                        <li>E-mailová adresa</li>
                        <li>Telefónne číslo</li>
                        <li>Heslo (uložené v šifrovanej forme)</li>
                    </ul>
                    
                    <h3>3.2 Údaje o aktivitách</h3>
                    <ul>
                        <li>História registrácií na lekcie a kurzy</li>
                        <li>Platobné transakcie a história kreditov</li>
                        <li>Preferencie a poznámky k lekciám</li>
                    </ul>
                    
                    <h3>3.3 Technické údaje</h3>
                    <ul>
                        <li>IP adresa</li>
                        <li>Informácie o prehliadači a zariadení</li>
                        <li>Údaje o návštevnosti webstránky</li>
                    </ul>
                    
                    <h2>4. Účel spracovávania údajov</h2>
                    <h3>4.1 Poskytovanie služieb</h3>
                    <p>Vaše údaje používame na:</p>
                    <ul>
                        <li>Správu vašeho účtu a rezervácií</li>
                        <li>Spracovanie platieb a správu kreditov</li>
                        <li>Komunikáciu o lekciách a kurzoch</li>
                        <li>Technickú podporu</li>
                    </ul>
                    
                    <h3>4.2 Marketingová komunikácia</h3>
                    <p>S vaším súhlasom môžeme posielať:</p>
                    <ul>
                        <li>Novinky o nových lekciách a kurzoch</li>
                        <li>Špeciálne ponuky a zľavy</li>
                        <li>Newsletter s tipmi o jóge a zdraví</li>
                    </ul>
                    
                    <h2>5. Právny základ spracovávania</h2>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Účel</th>
                                <th>Právny základ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Poskytovanie služieb</td>
                                <td>Plnenie zmluvy (čl. 6 ods. 1 písm. b GDPR)</td>
                            </tr>
                            <tr>
                                <td>Marketing</td>
                                <td>Súhlas (čl. 6 ods. 1 písm. a GDPR)</td>
                            </tr>
                            <tr>
                                <td>Účtovníctvo</td>
                                <td>Právna povinnosť (čl. 6 ods. 1 písm. c GDPR)</td>
                            </tr>
                            <tr>
                                <td>Zlepšovanie služieb</td>
                                <td>Oprávnený záujem (čl. 6 ods. 1 písm. f GDPR)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h2>6. Doba uchovávania údajov</h2>
                    <ul>
                        <li><strong>Registračné údaje:</strong> po celú dobu existencie účtu + 3 roky</li>
                        <li><strong>Transakčné údaje:</strong> 10 rokov (účtovná povinnosť)</li>
                        <li><strong>Marketingové súhlasy:</strong> do odvolania súhlasu</li>
                        <li><strong>Technické údaje:</strong> 2 roky</li>
                    </ul>
                    
                    <h2>7. Zdieľanie údajov s tretími stranami</h2>
                    <h3>7.1 Platobné služby</h3>
                    <p>Pre spracovanie platieb spolupracujeme s:</p>
                    <ul>
                        <li>Stripe (platby kartou)</li>
                        <li>GoPay (lokálne platobné metódy)</li>
                    </ul>
                    
                    <h3>7.2 E-mailové služby</h3>
                    <p>Na odosielanie e-mailov používame SendGrid.</p>
                    
                    <h3>7.3 Hosting a technické služby</h3>
                    <p>Naše dáta sú hostované u overených poskytovateľov v EÚ.</p>
                    
                    <h2>8. Vaše práva</h2>
                    <h3>8.1 Právo na prístup</h3>
                    <p>Máte právo požiadať o kópiu vašich osobných údajov.</p>
                    
                    <h3>8.2 Právo na opravu</h3>
                    <p>Môžete požiadať o opravu nesprávnych údajov vo vašom profile.</p>
                    
                    <h3>8.3 Právo na vymazanie</h3>
                    <p>Za určitých podmienok môžete požiadať o vymazanie vašich údajov.</p>
                    
                    <h3>8.4 Právo na obmedzenie</h3>
                    <p>Môžete požiadať o obmedzenie spracovávania vašich údajov.</p>
                    
                    <h3>8.5 Právo na prenosnosť</h3>
                    <p>Máte právo na získanie vašich údajov v štruktúrovanom formáte.</p>
                    
                    <h3>8.6 Právo namietať</h3>
                    <p>Môžete namietať proti spracovaniu na základe oprávneného záujmu.</p>
                    
                    <h3>8.7 Právo odvolať súhlas</h3>
                    <p>Súhlas s marketingovou komunikáciou môžete kedykoľvek odvolať.</p>
                    
                    <h2>9. Bezpečnosť údajov</h2>
                    <h3>9.1 Technické opatrenia</h3>
                    <ul>
                        <li>Šifrovanie údajov pri prenose (HTTPS)</li>
                        <li>Šifrovanie hesiel (bcrypt)</li>
                        <li>Zabezpečená databáza</li>
                        <li>Pravidelné bezpečnostné aktualizácie</li>
                    </ul>
                    
                    <h3>9.2 Organizačné opatrenia</h3>
                    <ul>
                        <li>Obmedzený prístup k údajom</li>
                        <li>Školenie zamestnancov</li>
                        <li>Monitorovanie prístupu</li>
                    </ul>
                    
                    <h2>10. Cookies a sledovacie technológie</h2>
                    <h3>10.1 Nevyhnutné cookies</h3>
                    <p>Používame cookies potrebné pre fungovanie stránky (prihlásenie, košík).</p>
                    
                    <h3>10.2 Analytické cookies</h3>
                    <p>S vaším súhlasom používame analytické nástroje na zlepšovanie služieb.</p>
                    
                    <h2>11. Prenos údajov mimo EÚ</h2>
                    <p>Vaše údaje spracovávame primárne v EÚ. V prípade prenosu mimo EÚ zabezpečujeme adekvátnu úroveň ochrany.</p>
                    
                    <h2>12. Zmeny zásad</h2>
                    <p>O zmenách týchto zásad vás budeme informovať e-mailom a oznámením na webstránke najmenej 30 dní vopred.</p>
                    
                    <h2>13. Kontakt</h2>
                    <p>Pre otázky ohľadom ochrany osobných údajov nás kontaktujte:</p>
                    <div class="bg-light p-3 rounded">
                        <strong>Email:</strong> privacy@laskavypriestor.eu<br>
                        <strong>Telefón:</strong> +421 123 456 789<br>
                        <strong>Adresa:</strong> Láskavý Priestor, s.r.o., Bratislava, Slovensko
                    </div>
                    
                    <h2>14. Dohľadový orgán</h2>
                    <p>Máte právo podať sťažnosť na Úrad na ochranu osobných údajov SR:</p>
                    <div class="bg-light p-3 rounded">
                        <strong>Úrad na ochranu osobných údajov SR</strong><br>
                        Hraničná 12, 820 07 Bratislava<br>
                        Tel.: +421 2 32 31 32 14<br>
                        Web: www.dataprotection.gov.sk
                    </div>
                    
                    <hr class="my-4">
                    <p class="text-muted small">
                        Tieto zásady nadobúdajú účinnosť dňom ich zverejnenia na webovej stránke.
                        Posledná aktualizácia: 10. júl 2025
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>