<?php
/**
 * API de Recursos de Lectura (artículos):  api/articles.php?action=...
 *   GET  list      (público: activos, paginado; staff con ?scope=admin: todos)
 *   GET  get       (?id= | ?slug=)  público ve activos; staff ve todo
 *   POST create    (staff)
 *   POST update    (staff)
 *   POST feature   (staff)  -> destacar/quitar
 *   POST delete    (staff = baja lógica; admin con ?hard=1 = definitivo)
 *   POST restore   (staff)
 *   GET  stats     (staff)
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

/** Da formato de salida a una fila de artículo. */
function article_out(array $r, bool $withContent = true): array
{
    $out = [
        'id'               => (int)$r['id'],
        'slug'             => $r['slug'],
        'title'            => $r['title'],
        'category'         => $r['category'],
        'excerpt'          => $r['excerpt'],
        'cover_url'        => !empty($r['cover_path']) ? $r['cover_path'] : null,
        'is_featured'      => (bool)$r['is_featured'],
        'feature_order'    => $r['feature_order'] !== null ? (int)$r['feature_order'] : null,
        'status'           => $r['status'],
        'meta_description' => $r['meta_description'],
        'view_count'       => (int)$r['view_count'],
        'published_at'     => $r['published_at'],
        'created_at'       => $r['created_at'],
        'updated_at'       => $r['updated_at'],
    ];
    if ($withContent) { $out['content'] = $r['content']; }
    return $out;
}

/** Recoge y valida los campos del cuerpo para crear/editar. */
function article_input(array $b): array
{
    $title = clean_str($b['title'] ?? '', 255);
    $content = (string)($b['content'] ?? '');
    if ($title === '' || trim($content) === '') {
        json_out(['ok' => false, 'error' => 'Título y contenido son obligatorios.'], 422);
    }
    $status = in_array($b['status'] ?? 'draft', ['draft', 'active', 'inactive'], true) ? $b['status'] : 'draft';

    return [
        'title'            => $title,
        'category'         => clean_str($b['category'] ?? '', 100),
        'excerpt'          => clean_str($b['excerpt'] ?? '', 1000),
        'content'          => mb_substr($content, 0, 60000),
        'cover_path'       => clean_str($b['cover_path'] ?? '', 255),
        'meta_description' => clean_str($b['meta_description'] ?? '', 320),
        'status'           => $status,
    ];
}

switch ($action) {

    // ---- LISTADO ----------------------------------------------------------
    case 'list': {
        require_method('GET');
        $adminScope = (($_GET['scope'] ?? '') === 'admin');
        if ($adminScope) require_role('admin', 'editor');

        $where = $adminScope ? "deleted_at IS NULL" : "status = 'active' AND deleted_at IS NULL";
        $params = [];

        if (($q = trim($_GET['q'] ?? '')) !== '') {
            $where .= " AND (title LIKE ? OR excerpt LIKE ? OR category LIKE ?)";
            $like = "%$q%";
            array_push($params, $like, $like, $like);
        }
        if (($cat = trim($_GET['category'] ?? '')) !== '' && $cat !== 'all') {
            $where .= " AND category = ?";
            $params[] = $cat;
        }

        $limit  = min(max((int)($_GET['limit'] ?? 12), 1), 100);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $countSt = db()->prepare("SELECT COUNT(*) FROM articles WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        // El listado no incluye el cuerpo completo (más liviano).
        $sql = "SELECT id, slug, title, category, excerpt, cover_path, is_featured, feature_order,
                       status, meta_description, view_count, published_at, created_at, updated_at
                FROM articles WHERE $where
                ORDER BY is_featured DESC, COALESCE(feature_order, 999999) ASC,
                         COALESCE(published_at, created_at) DESC, id DESC
                LIMIT $limit OFFSET $offset";
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = array_map(fn($r) => article_out($r, false), $st->fetchAll());

        json_out(['ok' => true, 'total' => $total, 'limit' => $limit, 'offset' => $offset, 'items' => $rows]);
    }

    // ---- DETALLE ----------------------------------------------------------
    case 'get': {
        require_method('GET');
        $id   = $_GET['id']   ?? null;
        $slug = $_GET['slug'] ?? null;
        if ($id) { $st = db()->prepare("SELECT * FROM articles WHERE id = ?");   $st->execute([(int)$id]); }
        elseif ($slug) { $st = db()->prepare("SELECT * FROM articles WHERE slug = ?"); $st->execute([$slug]); }
        else json_out(['ok' => false, 'error' => 'Falta id o slug.'], 422);

        $r = $st->fetch();
        if (!$r || $r['deleted_at']) json_out(['ok' => false, 'error' => 'Recurso no encontrado.'], 404);
        if ($r['status'] !== 'active' && !is_staff()) json_out(['ok' => false, 'error' => 'Recurso no disponible.'], 404);

        json_out(['ok' => true, 'item' => article_out($r)]);
    }

    // ---- CREAR ------------------------------------------------------------
    case 'create': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $in = article_input(body_json());

        $slug = unique_slug($in['title'], null, 'articles');
        $publishedAt = ($in['status'] === 'active') ? date('Y-m-d H:i:s') : null;

        $st = db()->prepare(
            "INSERT INTO articles
             (slug, title, category, excerpt, content, cover_path, status, meta_description,
              published_at, created_by, updated_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $slug, $in['title'], $in['category'] ?: null, $in['excerpt'], $in['content'],
            $in['cover_path'] ?: null, $in['status'], $in['meta_description'],
            $publishedAt, $u['id'], $u['id'],
        ]);
        $id = (int)db()->lastInsertId();
        audit('article.create', 'articles', $id, ['title' => $in['title']]);

        $row = db()->prepare("SELECT * FROM articles WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => article_out($row->fetch())], 201);
    }

    // ---- ACTUALIZAR -------------------------------------------------------
    case 'update': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

        $cur = db()->prepare("SELECT * FROM articles WHERE id = ?");
        $cur->execute([$id]);
        $existing = $cur->fetch();
        if (!$existing) json_out(['ok' => false, 'error' => 'Recurso no encontrado.'], 404);

        $in = article_input($b);

        // Fija published_at la primera vez que se activa.
        $publishedAt = $existing['published_at'];
        if ($in['status'] === 'active' && empty($publishedAt)) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $st = db()->prepare(
            "UPDATE articles SET
               title=?, category=?, excerpt=?, content=?, cover_path=?, status=?, meta_description=?,
               published_at=?, updated_by=?
             WHERE id=?"
        );
        $st->execute([
            $in['title'], $in['category'] ?: null, $in['excerpt'], $in['content'],
            ($in['cover_path'] !== '' ? $in['cover_path'] : $existing['cover_path']),
            $in['status'], $in['meta_description'], $publishedAt, $u['id'], $id,
        ]);
        audit('article.update', 'articles', $id, ['title' => $in['title']]);

        $row = db()->prepare("SELECT * FROM articles WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => article_out($row->fetch())]);
    }

    // ---- DESTACAR / QUITAR ------------------------------------------------
    case 'feature': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $featured = !empty($b['featured']) ? 1 : 0;
        $order  = isset($b['feature_order']) && $b['feature_order'] !== '' ? (int)$b['feature_order'] : null;
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

        db()->prepare("UPDATE articles SET is_featured=?, feature_order=? WHERE id=?")
            ->execute([$featured, $featured ? $order : null, $id]);
        audit('article.feature', 'articles', $id, ['featured' => (bool)$featured, 'order' => $order]);
        json_out(['ok' => true]);
    }

    // ---- DAR DE BAJA / ELIMINAR ------------------------------------------
    case 'delete': {
        require_method('POST');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? ($_GET['id'] ?? 0));
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $hard = (($_GET['hard'] ?? '') === '1');

        if ($hard) {
            require_role('admin');
            db()->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
            audit('article.hard_delete', 'articles', $id);
        } else {
            require_role('admin', 'editor');
            db()->prepare("UPDATE articles SET deleted_at = NOW(), status='inactive' WHERE id = ?")->execute([$id]);
            audit('article.soft_delete', 'articles', $id);
        }
        json_out(['ok' => true]);
    }

    case 'restore': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("UPDATE articles SET deleted_at = NULL, status='active' WHERE id = ?")->execute([$id]);
        audit('article.restore', 'articles', $id);
        json_out(['ok' => true]);
    }

    // ---- ESTADÍSTICAS -----------------------------------------------------
    case 'stats': {
        require_method('GET');
        require_role('admin', 'editor');
        $pdo = db();
        $total    = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE deleted_at IS NULL")->fetchColumn();
        $active   = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE deleted_at IS NULL AND status='active'")->fetchColumn();
        $featured = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE deleted_at IS NULL AND is_featured=1")->fetchColumn();
        json_out(['ok' => true, 'stats' => ['total' => $total, 'active' => $active, 'featured' => $featured]]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
