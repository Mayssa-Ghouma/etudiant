<?php
const CYCLES = ['Licence', 'Master', 'Doctorat'];
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
    $pdo = new PDO('mysql:host=10.15.15.8;dbname=siteweb;charset=utf8mb4', 'webuser', 'TON_MOT_DE_PASSE', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ]);
} catch (PDOException $e) {
    error_log('Erreur base : ' . $e->getMessage());
    http_response_code(503);
    exit('<p style="color:red">Base de données indisponible.</p>');
}

$erreurs = [];
$form = ['id' => '', 'nom' => '', 'prenom' => '', 'annee_universitaire' => '2026-2027', 'cycle' => 'Licence', 'specialite' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM etudiants WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: ./');
        exit;
    }
    foreach (['id', 'nom', 'prenom', 'annee_universitaire', 'cycle', 'specialite'] as $k) {
        $form[$k] = trim($_POST[$k] ?? '');
    }
    if ($form['nom'] === '' || $form['prenom'] === '') $erreurs[] = 'Nom et prénom obligatoires.';
    if (!preg_match('/^\d{4}-\d{4}$/', $form['annee_universitaire'])) $erreurs[] = "Année au format 2026-2027.";
    if (!in_array($form['cycle'], CYCLES, true)) $erreurs[] = 'Cycle invalide.';
    if ($form['cycle'] === 'Doctorat') $form['specialite'] = '';
    elseif ($form['specialite'] === '') $erreurs[] = 'Spécialité obligatoire (sauf doctorat).';

    if (!$erreurs) {
        $spec = $form['specialite'] === '' ? null : $form['specialite'];
        $vals = [$form['nom'], $form['prenom'], $form['annee_universitaire'], $form['cycle'], $spec];
        if ($action === 'update') {
            $pdo->prepare('UPDATE etudiants SET nom=?, prenom=?, annee_universitaire=?, cycle=?, specialite=? WHERE id=?')
                ->execute([...$vals, (int)$form['id']]);
        } else {
            $pdo->prepare('INSERT INTO etudiants (nom, prenom, annee_universitaire, cycle, specialite) VALUES (?,?,?,?,?)')
                ->execute($vals);
        }
        header('Location: ./');
        exit;
    }
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM etudiants WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    if ($row = $st->fetch()) { $form = array_merge($form, array_map(fn($v) => (string)$v, $row)); }
}

$etudiants = $pdo->query('SELECT * FROM etudiants ORDER BY id')->fetchAll();
$modif = $form['id'] !== '';
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Gestion des étudiants</title>
<style>
body{font-family:sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem}
table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:6px 10px;text-align:left}
th{background:#eef}form.inline{display:inline}.err{color:#b00}label{display:block;margin:.4rem 0}
</style></head><body>
<h1>Gestion des étudiants</h1>
<p><small>Serveur : <?= h(gethostname()) ?> (<?= h($_SERVER['SERVER_ADDR']) ?>)</small></p>

<table>
<tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Année</th><th>Cycle</th><th>Spécialité</th><th>Actions</th></tr>
<?php foreach ($etudiants as $e): ?>
<tr>
  <td><?= (int)$e['id'] ?></td><td><?= h($e['nom']) ?></td><td><?= h($e['prenom']) ?></td>
  <td><?= h($e['annee_universitaire']) ?></td><td><?= h($e['cycle']) ?></td><td><?= h($e['specialite'] ?? '—') ?></td>
  <td><a href="?edit=<?= (int)$e['id'] ?>">Modifier</a>
    <form class="inline" method="post" onsubmit="return confirm('Supprimer cet étudiant ?')">
      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
      <button>Supprimer</button></form></td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= $modif ? 'Modifier l\'étudiant n° ' . h($form['id']) : 'Ajouter un étudiant' ?></h2>
<?php foreach ($erreurs as $m): ?><p class="err"><?= h($m) ?></p><?php endforeach; ?>
<form method="post">
  <input type="hidden" name="action" value="<?= $modif ? 'update' : 'create' ?>">
  <input type="hidden" name="id" value="<?= h($form['id']) ?>">
  <label>Nom <input name="nom" value="<?= h($form['nom']) ?>" required maxlength="50"></label>
  <label>Prénom <input name="prenom" value="<?= h($form['prenom']) ?>" required maxlength="50"></label>
  <label>Année universitaire <input name="annee_universitaire" value="<?= h($form['annee_universitaire']) ?>" pattern="\d{4}-\d{4}" required></label>
  <label>Cycle <select name="cycle" id="cycle">
    <?php foreach (CYCLES as $c): ?><option <?= $form['cycle'] === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?>
  </select></label>
  <label>Spécialité <input name="specialite" id="spec" value="<?= h($form['specialite']) ?>" maxlength="100"></label>
  <button><?= $modif ? 'Enregistrer' : 'Ajouter' ?></button>
  <?php if ($modif): ?><a href="./">Annuler</a><?php endif; ?>
</form>
<script>
const cycle=document.getElementById('cycle'),spec=document.getElementById('spec');
function maj(){const d=cycle.value==='Doctorat';spec.disabled=d;if(d)spec.value='';}
cycle.addEventListener('change',maj);maj();
</script>
</body></html>