-- ============================================================================
--  FUNERARIA DEL ZULIA — Sistema de Obituarios en Línea
--  Fase 1: Esquema de Base de Datos (Supabase / PostgreSQL)
--  Ejecutar en: Supabase Dashboard -> SQL Editor -> New query -> Run
--  Idempotente en lo posible; pensado para una primera instalación limpia.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 0. EXTENSIONES
-- ----------------------------------------------------------------------------
create extension if not exists pgcrypto;      -- gen_random_uuid()

-- ----------------------------------------------------------------------------
-- 1. TIPOS ENUMERADOS
-- ----------------------------------------------------------------------------
do $$ begin
  create type public.user_role as enum ('admin', 'editor');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.obituary_status as enum ('draft', 'active', 'inactive');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.service_type as enum ('Velación', 'Cremación', 'Homenaje Póstumo', 'Traslado', 'Otro');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.condolence_status as enum ('pending', 'approved', 'hidden');
exception when duplicate_object then null; end $$;

-- ----------------------------------------------------------------------------
-- 2. FUNCIONES UTILITARIAS
-- ----------------------------------------------------------------------------

-- Mantiene updated_at en cada UPDATE
create or replace function public.set_updated_at()
returns trigger language plpgsql as $$
begin
  new.updated_at := now();
  return new;
end; $$;

-- Rol del usuario autenticado actual
create or replace function public.current_user_role()
returns public.user_role
language sql stable security definer set search_path = public as $$
  select role from public.profiles where id = auth.uid() and is_active;
$$;

create or replace function public.is_admin()
returns boolean
language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from public.profiles
    where id = auth.uid() and role = 'admin' and is_active
  );
$$;

create or replace function public.is_editor_or_admin()
returns boolean
language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from public.profiles
    where id = auth.uid() and role in ('admin','editor') and is_active
  );
$$;

-- ----------------------------------------------------------------------------
-- 3. TABLAS
-- ----------------------------------------------------------------------------

-- 3.1 Perfiles (extiende auth.users) ----------------------------------------
create table if not exists public.profiles (
  id          uuid primary key references auth.users(id) on delete cascade,
  email       text,
  full_name   text,
  role        public.user_role not null default 'editor',
  is_active   boolean not null default true,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
comment on table public.profiles is 'Usuarios del panel: admin (acceso total) y editor (gestiona obituarios y condolencias).';

-- 3.2 Plantillas de obituario -----------------------------------------------
create table if not exists public.obituary_templates (
  id          uuid primary key default gen_random_uuid(),
  name        text not null,
  description text,
  body_html   text not null default '',   -- plantilla con marcadores {{full_name}}, {{biography}}, {{death_date}}, etc.
  styles      text,                        -- CSS opcional específico de la plantilla
  config      jsonb not null default '{}', -- opciones (campos visibles, layout, etc.)
  is_default  boolean not null default false,
  is_active   boolean not null default true,
  created_by  uuid references public.profiles(id) on delete set null,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
comment on table public.obituary_templates is 'Plantillas editables por el administrador. Solo una puede ser la predeterminada.';

-- Solo una plantilla predeterminada a la vez
create unique index if not exists ix_templates_single_default
  on public.obituary_templates(is_default) where is_default;

-- 3.3 Obituarios -------------------------------------------------------------
create table if not exists public.obituaries (
  id               uuid primary key default gen_random_uuid(),
  slug             text unique,                       -- URL amigable para SEO
  full_name        text not null,
  birth_year       int,
  birth_date       date,
  death_date       date not null,
  service_type     public.service_type not null default 'Velación',
  location_name    text,
  location_address text,
  event_schedule   text,
  biography        text,
  -- Foto: se guarda en el DISCO del servidor (cPanel). Aquí solo la ruta/estado.
  photo_path        text,                 -- p.ej. 'uploads/obituarios/<id>.webp'
  photo_original_name text,
  photo_uploaded_at timestamptz,
  photo_purge_at    timestamptz,          -- fecha en que el cron debe purgar la foto
  photo_purged      boolean not null default false,
  -- Visibilidad / destacados
  is_pinned        boolean not null default false,    -- "fijar" en la página principal
  pin_order        int,
  status           public.obituary_status not null default 'active',
  template_id      uuid references public.obituary_templates(id) on delete set null,
  -- SEO / GEO
  meta_description text,
  view_count       int not null default 0,
  -- Auditoría básica
  created_by       uuid references public.profiles(id) on delete set null,
  updated_by       uuid references public.profiles(id) on delete set null,
  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now(),
  deleted_at       timestamptz                         -- borrado lógico
);
comment on table public.obituaries is 'Obituarios. La foto vive en disco; al purgarse se marca photo_purged y se usa un placeholder del logo. El registro permanece indexable (SEO/GEO).';

create index if not exists ix_obit_death_date on public.obituaries(death_date desc);
create index if not exists ix_obit_status     on public.obituaries(status);
create index if not exists ix_obit_pinned      on public.obituaries(is_pinned) where is_pinned;
create index if not exists ix_obit_purge       on public.obituaries(photo_purge_at) where photo_purged = false;
create index if not exists ix_obit_active_pub  on public.obituaries(death_date desc)
  where status = 'active' and deleted_at is null;

-- 3.4 Condolencias -----------------------------------------------------------
create table if not exists public.condolences (
  id           uuid primary key default gen_random_uuid(),
  obituary_id  uuid not null references public.obituaries(id) on delete cascade,
  author_name  text not null,
  message      text not null,
  status       public.condolence_status not null default 'pending',
  author_ip    inet,
  moderated_by uuid references public.profiles(id) on delete set null,
  moderated_at timestamptz,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
comment on table public.condolences is 'Mensajes del público. Entran como pending y un editor/admin los aprueba, edita u oculta.';
create index if not exists ix_cond_obituary on public.condolences(obituary_id);
create index if not exists ix_cond_status   on public.condolences(status);

-- 3.5 Ofrendas florales (paridad con el sistema actual; simuladas) ----------
create table if not exists public.flower_offerings (
  id          uuid primary key default gen_random_uuid(),
  obituary_id uuid not null references public.obituaries(id) on delete cascade,
  sender      text not null,
  flower_type text,
  price       text,
  message     text,
  created_at  timestamptz not null default now()
);
create index if not exists ix_flowers_obituary on public.flower_offerings(obituary_id);

-- 3.6 Configuración de la aplicación (incluye la purga) ----------------------
create table if not exists public.app_settings (
  key         text primary key,
  value       jsonb not null,
  description text,
  updated_by  uuid references public.profiles(id) on delete set null,
  updated_at  timestamptz not null default now()
);
comment on table public.app_settings is 'Configuración global. Controla la rutina de purga de fotos y la portada.';

-- 3.7 Bitácora de auditoría --------------------------------------------------
create table if not exists public.audit_log (
  id          bigint generated always as identity primary key,
  actor_id    uuid references public.profiles(id) on delete set null,
  actor_email text,
  action      text not null,           -- p.ej. 'obituaries.UPDATE', 'photo.purge', 'auth.login'
  entity_type text,
  entity_id   text,
  details     jsonb not null default '{}',
  ip_address  inet,
  user_agent  text,
  created_at  timestamptz not null default now()
);
comment on table public.audit_log is 'Trazabilidad: quién hizo qué y cuándo. Alimentada por triggers (cambios) y por el backend (login, purga).';
create index if not exists ix_audit_created on public.audit_log(created_at desc);
create index if not exists ix_audit_entity  on public.audit_log(entity_type, entity_id);
create index if not exists ix_audit_actor   on public.audit_log(actor_id);

-- ----------------------------------------------------------------------------
-- 4. TRIGGERS
-- ----------------------------------------------------------------------------

-- 4.1 updated_at en todas las tablas que lo tienen
create trigger trg_profiles_updated  before update on public.profiles
  for each row execute function public.set_updated_at();
create trigger trg_templates_updated before update on public.obituary_templates
  for each row execute function public.set_updated_at();
create trigger trg_obit_updated      before update on public.obituaries
  for each row execute function public.set_updated_at();
create trigger trg_cond_updated      before update on public.condolences
  for each row execute function public.set_updated_at();

-- 4.2 Calcular photo_purge_at cuando se asigna/cambia la foto
create or replace function public.set_photo_purge_at()
returns trigger language plpgsql as $$
declare v_days int;
begin
  if new.photo_path is not null and new.photo_path <> '' and new.photo_purged = false
     and (tg_op = 'INSERT' or new.photo_path is distinct from old.photo_path) then
    select coalesce((value #>> '{}')::int, 30) into v_days
      from public.app_settings where key = 'photo_retention_days';
    new.photo_uploaded_at := coalesce(new.photo_uploaded_at, now());
    new.photo_purge_at    := now() + (coalesce(v_days, 30)::text || ' days')::interval;
  end if;
  return new;
end; $$;

create trigger trg_obit_purge_at
  before insert or update of photo_path on public.obituaries
  for each row execute function public.set_photo_purge_at();

-- 4.3 Auditoría automática de cambios (INSERT/UPDATE/DELETE)
create or replace function public.audit_trigger()
returns trigger language plpgsql security definer set search_path = public as $$
declare
  v_actor uuid := auth.uid();
  v_email text;
  v_entity_id text;
begin
  select email into v_email from auth.users where id = v_actor;
  if tg_op = 'DELETE' then v_entity_id := old.id::text;
  else v_entity_id := new.id::text;
  end if;

  insert into public.audit_log(actor_id, actor_email, action, entity_type, entity_id, details)
  values (
    v_actor, v_email,
    tg_table_name || '.' || tg_op,
    tg_table_name, v_entity_id,
    case when tg_op = 'DELETE' then to_jsonb(old) else to_jsonb(new) end
  );

  if tg_op = 'DELETE' then return old; else return new; end if;
end; $$;

create trigger trg_audit_obit    after insert or update or delete on public.obituaries
  for each row execute function public.audit_trigger();
create trigger trg_audit_cond    after insert or update or delete on public.condolences
  for each row execute function public.audit_trigger();
create trigger trg_audit_tpl     after insert or update or delete on public.obituary_templates
  for each row execute function public.audit_trigger();
create trigger trg_audit_profile after insert or update or delete on public.profiles
  for each row execute function public.audit_trigger();
create trigger trg_audit_settings after insert or update or delete on public.app_settings
  for each row execute function public.audit_trigger();

-- 4.4 Evitar que un no-admin cambie su propio rol o estado
create or replace function public.protect_profile_privileges()
returns trigger language plpgsql security definer set search_path = public as $$
begin
  if not public.is_admin() then
    if new.role is distinct from old.role or new.is_active is distinct from old.is_active then
      raise exception 'No autorizado: solo un administrador puede cambiar el rol o el estado de un usuario.';
    end if;
  end if;
  return new;
end; $$;

create trigger trg_protect_profile before update on public.profiles
  for each row execute function public.protect_profile_privileges();

-- 4.5 Crear perfil automáticamente al registrarse un usuario en Auth
create or replace function public.handle_new_user()
returns trigger language plpgsql security definer set search_path = public as $$
begin
  insert into public.profiles (id, email, full_name, role)
  values (
    new.id,
    new.email,
    coalesce(new.raw_user_meta_data->>'full_name', new.email),
    coalesce((new.raw_user_meta_data->>'role')::public.user_role, 'editor')
  )
  on conflict (id) do nothing;
  return new;
end; $$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

-- ----------------------------------------------------------------------------
-- 5. FUNCIÓN PARA LA PORTADA (destacados + recientes)
-- ----------------------------------------------------------------------------
-- Devuelve primero los fijados (pin_order) y luego los más recientes,
-- hasta p_limit (por defecto 3). Llamable por el frontend vía RPC.
create or replace function public.homepage_obituaries(p_limit int default 3)
returns setof public.obituaries
language sql stable as $$
  select * from (
    select *, 0 as grp, coalesce(pin_order, 999999) as ord
      from public.obituaries
      where status = 'active' and deleted_at is null and is_pinned
    union all
    select *, 1 as grp, 0 as ord
      from public.obituaries
      where status = 'active' and deleted_at is null and not is_pinned
  ) q
  order by grp asc, ord asc, death_date desc
  limit greatest(p_limit, 0);
$$;

-- ----------------------------------------------------------------------------
-- 6. ROW LEVEL SECURITY
-- ----------------------------------------------------------------------------
alter table public.profiles            enable row level security;
alter table public.obituary_templates  enable row level security;
alter table public.obituaries          enable row level security;
alter table public.condolences         enable row level security;
alter table public.flower_offerings    enable row level security;
alter table public.app_settings        enable row level security;
alter table public.audit_log           enable row level security;

-- 6.1 profiles
create policy profiles_select_self_or_admin on public.profiles
  for select using (id = auth.uid() or public.is_admin());
create policy profiles_update_self_or_admin on public.profiles
  for update using (id = auth.uid() or public.is_admin());
create policy profiles_admin_insert on public.profiles
  for insert with check (public.is_admin());
create policy profiles_admin_delete on public.profiles
  for delete using (public.is_admin());

-- 6.2 obituary_templates
create policy templates_public_read_active on public.obituary_templates
  for select using (is_active or public.is_editor_or_admin());
create policy templates_admin_write on public.obituary_templates
  for all using (public.is_admin()) with check (public.is_admin());

-- 6.3 obituaries
create policy obit_public_read on public.obituaries
  for select using (
    (status = 'active' and deleted_at is null) or public.is_editor_or_admin()
  );
create policy obit_editor_insert on public.obituaries
  for insert with check (public.is_editor_or_admin());
create policy obit_editor_update on public.obituaries
  for update using (public.is_editor_or_admin()) with check (public.is_editor_or_admin());
create policy obit_admin_delete on public.obituaries
  for delete using (public.is_admin());

-- 6.4 condolences
create policy cond_public_read_approved on public.condolences
  for select using (status = 'approved' or public.is_editor_or_admin());
-- El público puede enviar condolencias; siempre entran como 'pending'
create policy cond_public_insert on public.condolences
  for insert with check (status = 'pending');
create policy cond_editor_update on public.condolences
  for update using (public.is_editor_or_admin()) with check (public.is_editor_or_admin());
create policy cond_editor_delete on public.condolences
  for delete using (public.is_editor_or_admin());

-- 6.5 flower_offerings
create policy flowers_public_insert on public.flower_offerings
  for insert with check (true);
create policy flowers_staff_read on public.flower_offerings
  for select using (public.is_editor_or_admin());
create policy flowers_admin_delete on public.flower_offerings
  for delete using (public.is_admin());

-- 6.6 app_settings (lectura para staff; escritura solo admin)
create policy settings_staff_read on public.app_settings
  for select using (public.is_editor_or_admin());
create policy settings_admin_write on public.app_settings
  for all using (public.is_admin()) with check (public.is_admin());

-- 6.7 audit_log (solo admin lee; nadie inserta directamente: lo hacen triggers/backend)
create policy audit_admin_read on public.audit_log
  for select using (public.is_admin());

-- ----------------------------------------------------------------------------
-- 7. DATOS SEMILLA
-- ----------------------------------------------------------------------------

-- 7.1 Configuración de purga y portada
insert into public.app_settings (key, value, description) values
  ('photo_retention_days', '30'::jsonb,
     'Días que se conserva la foto en el disco antes de purgarse.'),
  ('photo_purge_enabled', 'true'::jsonb,
     'Activa/desactiva la rutina automática de purga de fotos.'),
  ('photo_placeholder_path', '"uploads/obituarios/_placeholder.webp"'::jsonb,
     'Imagen (basada en el logo) que reemplaza a la foto purgada.'),
  ('homepage_recent_count', '3'::jsonb,
     'Cantidad de obituarios mostrados en la página principal.'),
  ('condolence_moderation', 'true'::jsonb,
     'Si es true, las condolencias requieren aprobación antes de publicarse.')
on conflict (key) do nothing;

-- 7.2 Plantillas iniciales (editables luego desde el panel)
insert into public.obituary_templates (name, description, is_default, body_html) values
  ('Clásico Sobrio',
   'Diseño tradicional centrado, ideal para la mayoría de los homenajes.',
   true,
   '<article class="obit-tpl obit-clasico"><div class="obit-cross">✝</div><h1>{{full_name}}</h1><p class="obit-dates">{{birth_year}} — {{death_date}}</p><p class="obit-qepd">Q.E.P.D.</p><div class="obit-photo">{{photo}}</div><p class="obit-bio">{{biography}}</p><div class="obit-service"><strong>{{service_type}}</strong><br>{{location_name}}<br>{{event_schedule}}</div></article>'),
  ('Estilo Periódico',
   'Columna estrecha tipo esquela de periódico, blanco y negro.',
   false,
   '<article class="obit-tpl obit-periodico"><h1>{{full_name}}</h1><hr><p class="obit-dates">{{birth_year}} - {{death_date}}</p><div class="obit-photo">{{photo}}</div><p class="obit-bio">{{biography}}</p><p class="obit-service">{{service_type}} · {{location_name}}</p><p>{{event_schedule}}</p></article>'),
  ('Homenaje Celestial',
   'Diseño con el ala/ángel de la marca y acentos en azul.',
   false,
   '<article class="obit-tpl obit-celestial"><div class="obit-wing"></div><h1>{{full_name}}</h1><p class="obit-dates">{{birth_year}} — {{death_date}}</p><div class="obit-photo">{{photo}}</div><blockquote class="obit-bio">{{biography}}</blockquote><div class="obit-service">{{service_type}} — {{location_name}}<br>{{event_schedule}}</div></article>')
on conflict do nothing;

-- ============================================================================
-- FIN DEL ESQUEMA
-- Siguiente paso: crear el primer ADMIN (ver database/README.md, sección 3).
-- ============================================================================
