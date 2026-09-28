```markdown
# AstroTherapia Client Management & Portal System
## System Architecture, Technical Specifications, and Implementation Blueprint

---

## 1. Executive Summary & Core Objectives

The goal of this system is to deploy a dual-surface web application directly integrated into `astrotherapia.com` (or hosted at `app.astrotherapia.com` with reverse-proxy routing at `/portal` and `/admin`). 

The system solves two distinct operational needs:
1. **The Practitioner Workspace (`/admin`)**: A dense, high-speed administrative interface for the astrologer to store client birth records, track empirical life events correlated with astrological timing techniques (Transits, Secondary Progressions, Solar Returns, Solar Arc Directions), take private consultation notes, and review/annotate client journal entries.
2. **The Client Portal (`/portal`)**: A calm, distraction-free, password-protected reflective space where clients review curated interpretations, stream session recordings, read synthesis reports, track active cyclical themes without technical jargon, and maintain an ongoing personal journal with selective practitioner sharing.

---

## 2. System Architecture & Tech Stack

### Recommended Stack
* **Framework**: Next.js 14+ (App Router) with TypeScript.
* **Styling**: Tailwind CSS + Shadcn UI (accessible, headless component primitives).
* **Database & ORM**: PostgreSQL with Prisma ORM or Drizzle ORM.
* **Authentication**: NextAuth.js (Auth.js) or Supabase Auth using role-based access control (`ROLE_ADMIN`, `ROLE_CLIENT`).
* **Object Storage**: AWS S3 or Cloudflare R2 (presigned URLs for audio recordings and PDF syntheses).
* **Astrological Calculations Engine**:
  * Option A: Python microservice wrapping `pyswisseph` (Swiss Ephemeris wrapper) or `flatlib`.
  * Option B: WebAssembly (Wasm) compiled Swiss Ephemeris or server-side Node.js wrapper (`swisseph` npm package).


```

```
                        [ Public Web / Edge Router ]
                                   │
             ┌─────────────────────┴─────────────────────┐
             ▼                                           ▼
   /admin/* (Role: Admin)                     /portal/* (Role: Client)

```

Practitioner Analytical Studio               Curated Client Reflection Space
│                                           │
└─────────────────────┬─────────────────────┘
│
[ Next.js API Layer / Server Actions ]
│
┌─────────────────────┼─────────────────────┐
▼                     ▼                     ▼
[ PostgreSQL DB ]     [ S3 / R2 Bucket ]   [ Ephemeris Engine ]
- Client Profiles     - Session MP3s       - Planeten/House Math
- Correlation Log     - Synthesis PDFs     - Transit Windows
- Journal & Feedback

```

---

## 3. Database Schema (PostgreSQL DDL)

```sql
-- Enums
CREATE TYPE user_role AS ENUM ('ADMIN', 'CLIENT');
CREATE TYPE house_system AS ENUM ('PLACIDUS', 'WHOLE_SIGN', 'EQUAL', 'REGIOMONTANUS', 'KOCH');
CREATE TYPE session_type AS ENUM ('NATAL_FOUNDATION', 'SOLAR_RETURN', 'TRANSIT_CHECKIN', 'SYNASTRY', 'VOCATIONAL');

-- Users table for authentication
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role user_role DEFAULT 'CLIENT',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Client biographical & natal profile
CREATE TABLE clients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    birth_date DATE NOT NULL,
    birth_time TIME NOT NULL,
    birth_time_accuracy_verified BOOLEAN DEFAULT FALSE,
    birth_city VARCHAR(255) NOT NULL,
    birth_country VARCHAR(100) NOT NULL,
    latitude DECIMAL(9,6) NOT NULL,
    longitude DECIMAL(9,6) NOT NULL,
    timezone_id VARCHAR(100) NOT NULL, -- e.g., 'Europe/Bucharest'
    utc_offset_hours DECIMAL(4,2) NOT NULL,
    preferred_house_system house_system DEFAULT 'PLACIDUS',
    dominant_elements JSONB,           -- e.g., {"fire": 3, "earth": 5, "air": 1, "water": 1}
    general_notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Empirical life events correlated with planetary activations
CREATE TABLE life_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    event_date DATE NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    event_description TEXT,
    astrological_factors JSONB NOT NULL,
    -- JSONB Structure Example:
    -- {
    --   "transits": ["Saturn conjunction IC (Exact)", "Jupiter square Sun"],
    --   "progressions": ["Sec. Prog. Moon 29 Leo entering 1st House"],
    --   "solar_return": ["SR Mars in 6th opposing SR Saturn"],
    --   "profection_year": "4th House Profection (Venus ruler)"
    -- }
    is_client_visible BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Consultation records and staged deliverables
CREATE TABLE sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    session_date TIMESTAMP WITH TIME ZONE NOT NULL,
    session_title VARCHAR(255) NOT NULL,
    session_type session_type DEFAULT 'NATAL_FOUNDATION',
    private_prep_notes TEXT,       -- Hidden from client; technical Markdown
    public_takeaways TEXT,         -- Published synthesis visible in client portal
    audio_file_url VARCHAR(512),    -- Presigned S3/R2 storage key
    report_pdf_url VARCHAR(512),    -- Presigned S3/R2 storage key
    is_published BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Curated transit themes published to the client portal
CREATE TABLE client_themes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,          -- e.g., "Structural Reorientation"
    technical_basis VARCHAR(255),         -- e.g., "Tr. Saturn square Natal Sun" (Admin only)
    client_summary TEXT NOT NULL,         -- Non-anxious psychological guidance
    start_window DATE,
    end_window DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Bi-directional reflective journal
CREATE TABLE client_journal_entries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    entry_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    topic_tag VARCHAR(100),               -- e.g., 'Career', 'Relationship', 'Dream', 'Somatic'
    content TEXT NOT NULL,
    is_shared_with_astrologer BOOLEAN DEFAULT TRUE,
    
    -- Practitioner Review Layer
    astrologer_internal_notes TEXT,       -- Technical correlations; visible only to admin
    astrologer_feedback TEXT,             -- Visible to client beneath entry
    feedback_published_at TIMESTAMP WITH TIME ZONE,
    is_read_by_astrologer BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Performance indices
CREATE INDEX idx_clients_user_id ON clients(user_id);
CREATE INDEX idx_life_events_client_id ON life_events(client_id);
CREATE INDEX idx_sessions_client_id ON sessions(client_id);
CREATE INDEX idx_client_themes_client_id ON client_themes(client_id);
CREATE INDEX idx_journal_entries_client_id ON client_journal_entries(client_id);
CREATE INDEX idx_journal_unread ON client_journal_entries(client_id, is_read_by_astrologer);

```

---

## 4. API Specification & Server Endpoints

All endpoints require JWT Bearer Authentication with role checks.

### Authentication & Core Access

* `POST /api/auth/login`
* Body: `{ email, password }`
* Returns: `{ user: { id, email, role }, token }`


* `GET /api/auth/me`
* Returns: Authenticated session profile.



### Practitioner Endpoints (`ROLE_ADMIN`)

* `GET /api/admin/clients`
* Supports query parameters: `?search=...&placement=...&unread_journal=true`


* `POST /api/admin/clients`
* Body: Birth coordinates, timezone, birth time, personal details.


* `GET /api/admin/clients/:id`
* Returns: Consolidated client payload (Bio, Natal Matrix, Life Events, Sessions, Themes, Journal).


* `POST /api/admin/clients/:id/life-events`
* Body: `{ event_date, event_title, event_description, astrological_factors, is_client_visible }`


* `POST /api/admin/clients/:id/sessions`
* Body: `{ session_date, session_title, session_type, private_prep_notes }`


* `PATCH /api/admin/sessions/:sessionId/publish`
* Body: `{ public_takeaways, audio_file_url, report_pdf_url, is_published: true }`


* `POST /api/admin/clients/:id/themes`
* Body: `{ title, technical_basis, client_summary, start_window, end_window, is_active }`


* `PATCH /api/admin/journal/:entryId/review`
* Body: `{ astrologer_internal_notes, astrologer_feedback, publish_feedback: boolean }`



### Client Portal Endpoints (`ROLE_CLIENT`)

* `GET /api/portal/dossier`
* Returns: Curated client profile, published sessions, active themes, and client-visible milestone timeline.


* `GET /api/portal/sessions/:id/stream`
* Returns: Short-lived presigned URL for secure session audio streaming.


* `GET /api/portal/journal`
* Returns: Array of journal entries created by the authenticated client.


* `POST /api/portal/journal`
* Body: `{ topic_tag, content, is_shared_with_astrologer }`


* `PUT /api/portal/journal/:id`
* Body: `{ topic_tag, content, is_shared_with_astrologer }` (Only editable if feedback is null).



---

## 5. User Interface Specifications

### 5.1 Practitioner Workspace (`/admin/clients/:id`)

* **Layout Structure**: 3-Column Studio Interface.
* **Left Column (Directory & Filters - Width: 280px)**:
* Search bar with instant fuzzy lookup.
* Client list card displaying: Full Name, Sun/Moon/Ascendant glyph indicators, date of upcoming consultation, and unread journal entry pill indicator (`● 2 new`).


* **Center Column (Analytical Matrix & Staging - Width: Flex / 60%)**:
* **Header**: Client Name, Natal Summary (14 May 1989, 04:22 EEST, Bucharest), House System picker dropdown.
* **Tabbed Workspace**:
* *Tab 1: Live Session Runner*: Split-pane Markdown editor with real-time word count and autosave. Left side: note-taking. Right side: real-time ephemeral transit calculations for the current date.
* *Tab 2: Client Journal Review*: Feed of incoming journal reflections submitted by the client. Includes inline review drawer: write private astrological correlations (e.g., `Tr. Mars opp. Sun in H6/H12`) and draft an optional supportive feedback note visible to the client.
* *Tab 3: Private Natal Synthesis*: Permanent psychological repository of client tendencies, structural vulnerabilities, and recurring archetypes.




* **Right Column (Correlation Engine & Portal Staging - Width: 380px)**:
* **Biographical Validation Log**: Chronological stack of life events. Each event card shows: Date, Milestone Name, and linked transit/progression tags. Includes button `[+ Correlate New Life Event]`.
* **Portal Publishing Dock**: File dropzone for MP3 and PDF. Textarea for "Public Takeaways & Core Anchors". Status toggle: `Draft (Private)` vs `Published to Client Portal`.





### 5.2 Client Portal (`/portal/dossier`)

* **Layout Structure**: Calm, single-column reading flow with clean typography (serif headings, generous whitespace, slate/cream background).
* **Hero Header**: Welcome banner with active overarching cyclical intention (e.g., *"Focus on consolidating boundaries and preparing for strategic restructuring."*).
* **Active Cyclical Themes Grid**: Cards displaying non-fatalistic transit summaries (e.g., Title: *"Structural Reorientation"*, Window: *"October 2026 – February 2027"*, Guidance: Plain-language psychological meaning).
* **Session Archive**:
* Embedded custom HTML5 Audio Player with 15s skip, playback speed (1x, 1.25x, 1.5x), and progress scrub bar.
* Download link for branded PDF synthesis.
* Card containing key session takeaways.


* **Reflective Journal Drawer & Feed**:
* Clean compose button `[+ Record Observation / Dream / Reflection]`.
* Form fields: Date, Focus Area dropdown (`General`, `Career & Ambition`, `Relationships`, `Dreams & Intuition`, `Emotional Shifts`), text editor, and toggle switch: `Share with Astrologer (Enabled by default)`.
* Entry cards displaying client text alongside an indented, softly accented sub-card for the Astrologer's grounding response when provided.


* **Life Anchors Timeline**: Read-only timeline displaying the client's past milestone years discussed in sessions to ground their current life stage.



---

## 6. Implementation Phase Roadmap

### Phase 1: Database Setup, Schema Migration & Authentication (Days 1–3)

1. Initialize Next.js project with TypeScript, Tailwind CSS, and Shadcn UI.
2. Configure PostgreSQL database via Docker or managed provider (Supabase/Neon).
3. Implement Prisma/Drizzle schema matching Section 3.
4. Set up NextAuth.js credentials provider with password hashing (`argon2` or `bcrypt`) and role-based middleware redirects (`/admin/*` restricted to `ROLE_ADMIN`).

### Phase 2: Practitioner Backend & Client Directory (Days 4–7)

1. Create CRUD API endpoints for `/api/admin/clients`.
2. Build 3-column layout shell in `/app/admin/layout.tsx`.
3. Implement client listing with search, sorting, and tag filtering in the left sidebar.
4. Build the client detail header, raw birth data inputs, and coordinate storage.

### Phase 3: Correlation Engine & Session Staging (Days 8–11)

1. Build the Life Events manager (CRUD modal + database insertion for `life_events`).
2. Implement JSONB form handler to tag multiple astrological factors (Transits, Progressions, Solar Return) to a single event.
3. Build the Session Management panel with private prep notes and public takeaways.
4. Integrate Cloudflare R2 / AWS S3 presigned upload endpoint for audio recordings and summary PDFs.

### Phase 4: Client Portal Development (Days 12–15)

1. Build `/app/portal/page.tsx` accessible only to `ROLE_CLIENT`.
2. Fetch and render published sessions (`is_published = true`), audio stream player, and active cyclical themes.
3. Build the Life Anchors timeline component from client-visible life events.

### Phase 5: Bi-directional Reflective Journal (Days 16–18)

1. Implement journal entry creation form in the client portal (`/portal/journal`).
2. Add feed display showing client entries and practitioner feedback cards.
3. Build the journal review interface in the admin panel (`/admin/clients/:id` -> Journal Tab).
4. Implement review actions: save internal technical notes, draft client-visible feedback, and mark entry as read.

### Phase 6: Astrological Engine Integration & Polish (Days 19–21)

1. Connect Swiss Ephemeris microservice or library to auto-calculate planetary positions, houses, and active transits on demand.
2. Conduct end-to-end security audit: verify that client users cannot query administrative endpoints or view unpublished sessions, internal notes, or other clients' dossiers.
3. Optimize performance: implement optimistic UI updates for journal entries and notes, and add audio player persistence across page tabs.

---

## 7. Data Privacy, Encryption & Security Standards

1. **Confidentiality Scope**: Astrological and psychological session notes constitute sensitive personal reflection data.
2. **Access Separation**:
* All database queries for `/portal` routes MUST be scoped to `session.user.id`.
* Columns `private_prep_notes` and `astrologer_internal_notes` MUST NEVER be selected or returned in API serializers served to client roles.


3. **Media Protection**:
* Session recordings (MP3s) and PDF syntheses MUST NOT be stored in public buckets.
* Access must be mediated via short-lived (15-minute expiration) presigned URLs generated server-side after session verification.


4. **Transport & Data at Rest**:
* Enforce TLS 1.3 in transit.
* Database storage encryption enabled at rest (AES-256).



```

```