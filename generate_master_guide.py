import os
import sys
from reportlab.lib.pagesizes import letter
from reportlab.lib import colors
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether, HRFlowable
)
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.pdfgen import canvas

class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        super(NumberedCanvas, self).__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_page_decorations(num_pages)
            super(NumberedCanvas, self).showPage()
        super(NumberedCanvas, self).save()

    def draw_page_decorations(self, page_count):
        self.saveState()
        self.setFont("Helvetica-Bold", 8)
        self.setFillColor(colors.HexColor("#0f766e"))
        
        # Header (pages > 1)
        if self._pageNumber > 1:
            self.drawString(40, 755, "MediToken - A to Z Master Project Viva & Technical Guide")
            self.setFont("Helvetica", 8)
            self.setFillColor(colors.HexColor("#64748b"))
            self.drawRightString(572, 755, "Doctor Appointment & Digital Token Queue System")
            self.setStrokeColor(colors.HexColor("#cbd5e1"))
            self.setLineWidth(0.5)
            self.line(40, 748, 572, 748)
            
        # Footer
        page_text = f"Page {self._pageNumber} of {page_count}"
        self.setFont("Helvetica", 8)
        self.setFillColor(colors.HexColor("#64748b"))
        self.drawRightString(572, 25, page_text)
        self.drawString(40, 25, "MediToken Academic Capstone Project - Confidential Viva Preparation Document")
        self.setStrokeColor(colors.HexColor("#cbd5e1"))
        self.setLineWidth(0.5)
        self.line(40, 35, 572, 35)
        
        self.restoreState()

def build_pdf(filename="d:/6486/MediToken_A_to_Z_Complete_Guide.pdf"):
    doc = SimpleDocTemplate(
        filename,
        pagesize=letter,
        leftMargin=40,
        rightMargin=40,
        topMargin=45,
        bottomMargin=45
    )
    
    styles = getSampleStyleSheet()
    
    primary = colors.HexColor("#0f766e")    # Emerald / Teal
    secondary = colors.HexColor("#0284c7")  # Ocean Blue
    dark = colors.HexColor("#0f172a")       # Slate 900
    text_color = colors.HexColor("#1e293b") # Slate 800
    muted = colors.HexColor("#475569")      # Slate 600
    card_bg = colors.HexColor("#f8fafc")    # Slate 50
    border_color = colors.HexColor("#cbd5e1")
    hindi_highlight = colors.HexColor("#b45309") # Warm Amber for Hindi meaning
    
    title_style = ParagraphStyle(
        'DocTitle',
        parent=styles['Heading1'],
        fontName='Helvetica-Bold',
        fontSize=20,
        leading=24,
        textColor=primary,
        alignment=1,
        spaceAfter=4
    )
    
    subtitle_style = ParagraphStyle(
        'DocSubTitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=10.5,
        leading=14,
        textColor=secondary,
        alignment=1,
        spaceAfter=10
    )
    
    h1_style = ParagraphStyle(
        'SectionH1',
        parent=styles['Heading2'],
        fontName='Helvetica-Bold',
        fontSize=12,
        leading=16,
        textColor=primary,
        spaceBefore=10,
        spaceAfter=4,
        keepWithNext=True
    )

    h2_style = ParagraphStyle(
        'SectionH2',
        parent=styles['Heading3'],
        fontName='Helvetica-Bold',
        fontSize=10,
        leading=14,
        textColor=secondary,
        spaceBefore=7,
        spaceAfter=3,
        keepWithNext=True
    )
    
    body_style = ParagraphStyle(
        'DocBody',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8.5,
        leading=12.5,
        textColor=text_color,
        spaceAfter=4
    )
    
    body_bold = ParagraphStyle(
        'DocBodyBold',
        parent=body_style,
        fontName='Helvetica-Bold'
    )

    hindi_meaning_style = ParagraphStyle(
        'HindiMeaning',
        parent=styles['Normal'],
        fontName='Helvetica-Oblique',
        fontSize=8.5,
        leading=12.5,
        textColor=colors.HexColor("#065f46"), # Forest Green for Hindi Meaning
        spaceAfter=4
    )
    
    q_style = ParagraphStyle(
        'QStyle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=9,
        leading=13,
        textColor=primary,
        spaceBefore=6,
        spaceAfter=2,
        keepWithNext=True
    )
    
    ans_style = ParagraphStyle(
        'AnsStyle',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8.5,
        leading=12,
        textColor=text_color,
        spaceAfter=4
    )
    
    elements = []
    
    # Title Header Banner
    elements.append(Paragraph("MEDITOKEN - A TO Z COMPLETE MASTER GUIDE", title_style))
    elements.append(Paragraph("Full Technical Architecture, File Breakdown, Stack Analysis & Viva Q&A (Hindi Meaning Explained)", subtitle_style))
    elements.append(HRFlowable(width="100%", thickness=1.5, color=primary, spaceAfter=8))
    
    # Quick Summary Box
    overview_text = (
        "<b>Project Title:</b> MediToken - Doctor Appointment Booking & Intelligent Digital Token Queue System<br/>"
        "<b>Academic Level:</b> Final Year IT / CS Capstone Project | <b>Type:</b> Full-Stack Database Web Application<br/>"
        "<b>Tech Stack:</b> HTML5, Vanilla CSS3, JavaScript AJAX (Frontend) | PHP 8.2 & PDO (Backend) | MySQL / MariaDB (Database)<br/>"
        "<b>Deployment:</b> Docker + Apache MPM Prefork on Railway Cloud (Live: <u>meditoken.up.railway.app</u>) & Localhost (XAMPP)"
    )
    overview_table = Table([[Paragraph(overview_text, body_style)]], colWidths=[532])
    overview_table.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), colors.HexColor("#f0fdf4")),
        ('BOX', (0,0), (-1,-1), 1, colors.HexColor("#86efac")),
        ('TOPPADDING', (0,0), (-1,-1), 5),
        ('BOTTOMPADDING', (0,0), (-1,-1), 5),
        ('LEFTPADDING', (0,0), (-1,-1), 8),
        ('RIGHTPADDING', (0,0), (-1,-1), 8),
    ]))
    elements.append(overview_table)
    elements.append(Spacer(1, 6))
    
    # PART 1: CORE CONCEPT
    elements.append(Paragraph("1. Executive Summary & Problem vs Solution (Kyu Banaya?)", h1_style))
    p1 = (
        "<b>The Real-World Problem:</b> In outpatient departments (OPDs), polyclinics, and hospitals, manual queue management "
        "relies heavily on paper tokens and handwritten register logs. This creates overcrowding in waiting halls, missing paper slips, "
        "double-booking errors, and zero visibility into expected waiting times.<br/>"
        "<b>Hindi Meaning (Iska seedha matlab):</b> Hospital ke OPD me sabse badi pareshani lambi lines, dhakka-mukki, aur kagaz ke tokens ka khona hai. "
        "Mareez ko ghanto bina kisi jankari ke baithna padta hai ki uska number kab aayega.<br/>"
        "<b>The MediToken Solution:</b> A 100% database-driven web application that automates the consultation lifecycle. "
        "Patients book slots online, receive algorithmic digital tokens (e.g. `A-001`), and monitor live queue progress remotely from their smartphones. "
        "Doctors operate an interactive queue desk to call tokens in a single click, while Administrators monitor clinic metrics across all departments.<br/>"
        "<b>Hindi Meaning:</b> MediToken me mareez ghar baithe mobile se doctor chun kar slot book karta hai, use digital token milta hai, "
        "aur mobile par live dikhta hai ki abhi doctor kis mareez ko dekh raha hai aur uska number kab aayega."
    )
    elements.append(Paragraph(p1, body_style))
    elements.append(Spacer(1, 4))
    
    # PART 2: TECH STACK BREAKDOWN
    elements.append(Paragraph("2. Full Technology Stack - Kahan Kya Use Hua Hai?", h1_style))
    
    tech_table_data = [
        [Paragraph("<b>Component / Layer</b>", body_bold), Paragraph("<b>Technology Used</b>", body_bold), Paragraph("<b>Technical Purpose & Hindi Meaning (Kahan & Kyu Use Hua?)</b>", body_bold)],
        [
            Paragraph("<b>Frontend Markup</b>", body_style),
            Paragraph("HTML5 Semantic", body_style),
            Paragraph("Provides structured, standards-compliant layout (`<nav>`, `<header>`, `<main>`, `<section>`, `<aside>`).<br/><b>Hindi Meaning:</b> Web pages ka structure aur forms banane ke liye use hua hai.", body_style)
        ],
        [
            Paragraph("<b>Frontend Styling</b>", body_style),
            Paragraph("Vanilla CSS3", body_style),
            Paragraph("Custom emerald medical theme (`#0f766e`, `#f8fafc`). Responsive CSS Flexbox & Grid layouts. No heavy external frameworks (Bootstrap/Tailwind) to maximize speed.<br/><b>Hindi Meaning:</b> Website ko beautiful, fast aur responsive design dene ke liye custom CSS likhi gayi hai.", body_style)
        ],
        [
            Paragraph("<b>Frontend Logic</b>", body_style),
            Paragraph("Vanilla JavaScript (ES6+ AJAX)", body_style),
            Paragraph("Handles client-side slot selection, modal dialogs, and automated 4-second background queue polling using the native `fetch()` API.<br/><b>Hindi Meaning:</b> Bina page refresh kiye screen par live token update karne aur buttons ko interactive banane ke liye.", body_style)
        ],
        [
            Paragraph("<b>Backend Engine</b>", body_style),
            Paragraph("PHP 8.2+", body_style),
            Paragraph("Server-side request routing, session authentication, token numbering logic, slot concurrency checks, and REST-style JSON API endpoints.<br/><b>Hindi Meaning:</b> Website ka main dimag — login check karna, token banana aur server par sara logic chalana.", body_style)
        ],
        [
            Paragraph("<b>Database Access</b>", body_style),
            Paragraph("PHP Data Objects (PDO)", body_style),
            Paragraph("Parameterized prepared statements (`prepare()` + `execute()`) with emulated prepares disabled to prevent SQL Injection completely.<br/><b>Hindi Meaning:</b> Database se judne ka sabse secure tarika jo hacker ko SQL Injection karne se rokta hai.", body_style)
        ],
        [
            Paragraph("<b>Database Management</b>", body_style),
            Paragraph("MySQL / MariaDB", body_style),
            Paragraph("Relational schema in 3rd Normal Form (3NF) with 5 tables, foreign keys with `ON DELETE CASCADE`, and composite unique constraints.<br/><b>Hindi Meaning:</b> Saare mareezon, doctoron aur appointments ka data permanently store karne ke liye.", body_style)
        ],
        [
            Paragraph("<b>Security Hashing</b>", body_style),
            Paragraph("Bcrypt (`PASSWORD_BCRYPT`)", body_style),
            Paragraph("One-way cryptographic password hashing via `password_hash()` and `password_verify()`.<br/><b>Hindi Meaning:</b> Password ko 60-akshar ke gupt code me badalna taaki admin ya hacker bhi asli password na jaan sake.", body_style)
        ],
        [
            Paragraph("<b>DevOps & Cloud</b>", body_style),
            Paragraph("Docker + Railway Cloud", body_style),
            Paragraph("Custom `Dockerfile` on `php:8.2-apache` with dynamic port binding in `docker-entrypoint.sh` hosted on Railway Cloud.<br/><b>Hindi Meaning:</b> Website ko 24 ghante internet par live chalane ke liye Docker aur Railway cloud hosting use hui hai.", body_style)
        ]
    ]
    t_tech = Table(tech_table_data, colWidths=[90, 105, 337])
    t_tech.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), colors.HexColor("#0f766e")),
        ('TEXTCOLOR', (0,0), (-1,0), colors.white),
        ('GRID', (0,0), (-1,-1), 0.5, border_color),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, card_bg]),
        ('TOPPADDING', (0,0), (-1,-1), 3),
        ('BOTTOMPADDING', (0,0), (-1,-1), 3),
        ('LEFTPADDING', (0,0), (-1,-1), 5),
        ('RIGHTPADDING', (0,0), (-1,-1), 5),
    ]))
    elements.append(t_tech)
    elements.append(Spacer(1, 6))

    # PART 3: REPO FILE STRUCTURE EXPLAINED
    elements.append(Paragraph("3. Complete Codebase & File Breakdown - Har File Ka Kaam Kya Hai?", h1_style))
    
    file_table_data = [
        [Paragraph("<b>File / Folder Path</b>", body_bold), Paragraph("<b>Module / Layer</b>", body_bold), Paragraph("<b>Detailed Function & Hindi Meaning (Iska Kaam Kya Hai?)</b>", body_bold)],
        [
            Paragraph("<b>index.php</b>", body_style),
            Paragraph("Public Landing Page", body_style),
            Paragraph("Public homepage showcasing features, quick action buttons, and public live token queue preview banner.<br/><b>Hindi Meaning:</b> Website ka main front page jahan se booking aur details shuru hoti hain.", body_style)
        ],
        [
            Paragraph("<b>login.php & register.php</b>", body_style),
            Paragraph("Authentication", body_style),
            Paragraph("Multi-role unified login (Patient, Doctor, Admin) and patient registration with client validation and Bcrypt hashing.<br/><b>Hindi Meaning:</b> Naye mareez ka registration aur teeno roles ka login page.", body_style)
        ],
        [
            Paragraph("<b>doctors.php</b>", body_style),
            Paragraph("Doctor Catalog", body_style),
            Paragraph("Displays verified doctor cards, medical specializations, consultation hours, and live availability status.<br/><b>Hindi Meaning:</b> Clinic ke sabhi doctors ki list unki specialization aur timing ke sath.", body_style)
        ],
        [
            Paragraph("<b>config/database.php</b>", body_style),
            Paragraph("Database Core", body_style),
            Paragraph("Environment variable loader (`.env` or Railway cloud variables) and singleton PDO MySQL connection instance.<br/><b>Hindi Meaning:</b> Database se connection jodna chahe localhost par ho ya Railway cloud par.", body_style)
        ],
        [
            Paragraph("<b>includes/auth.php</b>", body_style),
            Paragraph("Session Security", body_style),
            Paragraph("Session validation, `is_logged_in()`, `current_user()`, and `require_role()` role-based access control guards.<br/><b>Hindi Meaning:</b> Bina permission ke kisi ko unauthorized page par jane se rokna.", body_style)
        ],
        [
            Paragraph("<b>includes/functions.php</b>", body_style),
            Paragraph("Business Logic", body_style),
            Paragraph("Algorithmic token generator (`A-001`), double-booking slot checks, notification logger, and flash messages.<br/><b>Hindi Meaning:</b> Naya token banana aur system ka sara dimagi logic chalana.", body_style)
        ],
        [
            Paragraph("<b>patient/book_appointment.php</b>", body_style),
            Paragraph("Patient Wizard", body_style),
            Paragraph("Dynamic booking flow: Select doctor -> pick date -> choose time slot -> atomic transaction token generation.<br/><b>Hindi Meaning:</b> Mareez ka appointment book karne aur naya token lene ka page.", body_style)
        ],
        [
            Paragraph("<b>patient/queue.php</b>", body_style),
            Paragraph("Live Queue Tracker", body_style),
            Paragraph("Real-time live queue wallboard polling `api/queue_status.php` every 4 seconds. Displays 'Now Serving', 'Your Token', and 'Estimated Wait Time'.<br/><b>Hindi Meaning:</b> Mareez ka live token dekhna ki abhi kiska checkup chal raha hai aur mera number kab aayega.", body_style)
        ],
        [
            Paragraph("<b>doctor/queue.php</b>", body_style),
            Paragraph("Doctor OPD Desk", body_style),
            Paragraph("OPD management desk. Doctors execute single-click 'Call Next' (transitions token to Serving), 'Complete', and 'Skip/No-Show'.<br/><b>Hindi Meaning:</b> Doctor ke room ka control panel jahan se wo 'Call Next' dabakar agle mareez ko bulata hai.", body_style)
        ],
        [
            Paragraph("<b>admin/dashboard.php</b>", body_style),
            Paragraph("Admin Control Panel", body_style),
            Paragraph("Clinic-wide KPI metrics: Total Doctors, Total Patients, Today's Bookings, and quick management links.<br/><b>Hindi Meaning:</b> Clinic ke pure kaam ka summary dashboard.", body_style)
        ],
        [
            Paragraph("<b>admin/doctors.php</b>", body_style),
            Paragraph("Doctor CRUD", body_style),
            Paragraph("Admin panel to add new consulting doctors, edit profiles, configure consultation hours, and toggle active status.<br/><b>Hindi Meaning:</b> Naye doctor add karna, timing badalna aur doctor ki details manage karna.", body_style)
        ],
        [
            Paragraph("<b>api/queue_status.php</b>", body_style),
            Paragraph("REST JSON API", body_style),
            Paragraph("High-performance JSON endpoint returning the current serving token, waiting queue length, and last updated timestamp.<br/><b>Hindi Meaning:</b> JavaScript ko live token data dene wala chota API program.", body_style)
        ],
        [
            Paragraph("<b>api/cancel_appointment.php</b>", body_style),
            Paragraph("REST JSON API", body_style),
            Paragraph("Processes appointment cancellations, verifies role permissions, updates MySQL status, and dispatches in-app notifications.<br/><b>Hindi Meaning:</b> Mareez ya doctor dwara appointment cancel karne ka API handler.", body_style)
        ],
        [
            Paragraph("<b>Dockerfile & entrypoint.sh</b>", body_style),
            Paragraph("DevOps / Cloud", body_style),
            Paragraph("Packages PHP-Apache container, enables MPM prefork, binds dynamic Railway `$PORT`, and starts server.<br/><b>Hindi Meaning:</b> Railway cloud par website ko deploy karke live chalane wali files.", body_style)
        ]
    ]
    t_files = Table(file_table_data, colWidths=[105, 95, 332])
    t_files.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), colors.HexColor("#0284c7")),
        ('TEXTCOLOR', (0,0), (-1,0), colors.white),
        ('GRID', (0,0), (-1,-1), 0.5, border_color),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, card_bg]),
        ('TOPPADDING', (0,0), (-1,-1), 3),
        ('BOTTOMPADDING', (0,0), (-1,-1), 3),
        ('LEFTPADDING', (0,0), (-1,-1), 5),
        ('RIGHTPADDING', (0,0), (-1,-1), 5),
    ]))
    elements.append(t_files)
    elements.append(Spacer(1, 6))

    # PAGE BREAK FOR DATABASE & VIVA QA
    elements.append(PageBreak())

    # PART 4: DATABASE SCHEMA
    elements.append(Paragraph("4. Relational Database Schema (`meditoken_db` - 5 Core Tables)", h1_style))
    db_text = (
        "The relational database is designed strictly in <b>3rd Normal Form (3NF)</b> with 5 tables. "
        "Every table has a Primary Key, Foreign Key constraints with `ON DELETE CASCADE`, and strict indexes:"
    )
    elements.append(Paragraph(db_text, body_style))
    
    db_schema_data = [
        [Paragraph("<b>Table Name</b>", body_bold), Paragraph("<b>Columns & Data Types</b>", body_bold), Paragraph("<b>Keys & Constraints (Hindi Meaning)</b>", body_bold)],
        [
            Paragraph("<b>patients</b>", body_style),
            Paragraph("`patient_id` INT PK AI, `name` VARCHAR(100), `mobile_no` VARCHAR(20), `email` VARCHAR(100) UNIQUE, `password` VARCHAR(255), `created_at` DATETIME", body_style),
            Paragraph("Stores patient credentials. `email` is UNIQUE to prevent duplicate accounts.<br/><b>Hindi:</b> Sabhi registered mareezon ka record.", body_style)
        ],
        [
            Paragraph("<b>doctors</b>", body_style),
            Paragraph("`doctor_id` INT PK AI, `doctor_name` VARCHAR(100), `email` VARCHAR(100) UNIQUE, `password` VARCHAR(255), `specialization` VARCHAR(100), `available_time` VARCHAR(100), `availability_status` ENUM", body_style),
            Paragraph("Stores consulting medical practitioners and their daily consultation hours.<br/><b>Hindi:</b> Sabhi doctoron ki details aur timing.", body_style)
        ],
        [
            Paragraph("<b>appointments</b><br/><i>(Central Table)</i>", body_style),
            Paragraph("`appointment_id` INT PK AI, `patient_id` INT FK, `doctor_id` INT FK, `appointment_date` DATE, `appointment_time` VARCHAR(20), `token_number` VARCHAR(20), `status` ENUM('Waiting','Serving','Completed','Cancelled','Skipped')", body_style),
            Paragraph("Junction table. Foreign keys cascade on delete. Composite UNIQUE index on `(doctor_id, appointment_date, token_number)`.<br/><b>Hindi:</b> Sabse main table — kis mareez ka kis doctor se kis time par kaun sa token hai.", body_style)
        ],
        [
            Paragraph("<b>admins</b>", body_style),
            Paragraph("`admin_id` INT PK AI, `username` VARCHAR(50) UNIQUE, `password` VARCHAR(255), `created_at` DATETIME", body_style),
            Paragraph("Clinic administrative governance credentials.<br/><b>Hindi:</b> Clinic ke main admin ka login record.", body_style)
        ],
        [
            Paragraph("<b>notifications</b>", body_style),
            Paragraph("`notification_id` INT PK AI, `patient_id` INT FK, `doctor_id` INT, `appointment_id` INT, `message` VARCHAR(255), `type` VARCHAR(50), `is_read` TINYINT(1)", body_style),
            Paragraph("Audit trail of queue events delivered to patient dashboard.<br/><b>Hindi:</b> Token call hone aur status badalne ke alerts.", body_style)
        ]
    ]
    t_db = Table(db_schema_data, colWidths=[90, 240, 202])
    t_db.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), colors.HexColor("#0f766e")),
        ('TEXTCOLOR', (0,0), (-1,0), colors.white),
        ('GRID', (0,0), (-1,-1), 0.5, border_color),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, card_bg]),
        ('TOPPADDING', (0,0), (-1,-1), 3),
        ('BOTTOMPADDING', (0,0), (-1,-1), 3),
        ('LEFTPADDING', (0,0), (-1,-1), 5),
        ('RIGHTPADDING', (0,0), (-1,-1), 5),
    ]))
    elements.append(t_db)
    elements.append(Spacer(1, 6))

    # PART 5: TOP 15 VIVA Q&A WITH HINDI MEANING
    elements.append(Paragraph("5. Top 15 Viva Questions & Winning Answers (With Hindi Meaning)", h1_style))
    elements.append(Paragraph("Examiner ke pooche jaane wale sabse common sawal aur unke perfect technical answers with simple Hindi meaning:", subtitle_style))
    elements.append(HRFlowable(width="100%", thickness=1, color=primary, spaceAfter=6))

    qa_list = [
        ("Q1. What is the main objective of MediToken?",
         "MediToken is a full-stack digital queue and appointment booking platform designed to eliminate manual paper slips, avoid physical overcrowding in OPDs, prevent double-bookings, and enable real-time queue tracking from remote devices.",
         "MediToken ka matlab hai clinic me OPD ki bheed aur kagaz ke tokens ko khatam karke pura process mobile par digital banana, taaki mareez ko live pata chale uska number kab aayega."),
        
        ("Q2. How is the Token Number generated? Can duplicate tokens occur?",
         "Tokens are generated algorithmically per doctor per date. The system prefixes the doctor's initial letter (e.g. Dr. Amit -> 'A') and queries the maximum assigned sequence (`SELECT MAX(token_number)`), incrementing it by 1 (e.g. A-001, A-002). Duplicates are prevented at the database level by a composite UNIQUE constraint on `(doctor_id, appointment_date, token_number)`.",
         "Token doctor ke pehle letter se banta hai (Dr. Amit -> A-001). Database me unique key lagi hai isliye do logon ko kabhi ek hi token nahi mil sakta."),
        
        ("Q3. How does the live queue update in real-time without page reload?",
         "We implemented AJAX Long Polling using the JavaScript `fetch()` API. Every 4 seconds, the client sends an asynchronous background request to `api/queue_status.php`. The backend queries the current serving token and remaining queue count, returning lightweight JSON. The DOM updates dynamically without screen flicker.",
         "JavaScript har 4 second me peeche se server se poochti hai ki abhi kaun sa number chal raha hai aur screen par bina refresh kiye naya token dikha deti hai."),

        ("Q4. How did you secure the application against SQL Injection?",
         "We strictly use PHP Data Objects (PDO) with parameterized prepared statements (`$stmt->prepare()` and `$stmt->execute([$params])`). Furthermore, `PDO::ATTR_EMULATE_PREPARES` is set to `false`, ensuring SQL queries are pre-compiled on the database engine before user input is bound.",
         "Humne PDO Prepared Statements use kiye hain, jisse user ka input seedha SQL me nahi judta aur website hack nahi ho sakti."),

        ("Q5. How are user passwords secured?",
         "Passwords are never stored in plaintext. They are encrypted using the Bcrypt hashing algorithm via PHP's native `password_hash($password, PASSWORD_BCRYPT)` function with automatic salting. During authentication, `password_verify()` validates the hash securely.",
         "Passwords ko 60-character ke gupt code (hash) me convert karke save kiya jata hai, jisse koi hacker ya admin bhi asli password nahi dekh sakta."),

        ("Q6. How is double-booking prevented for the same slot?",
         "During booking, the system executes an atomic concurrency check: `SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status != 'Cancelled'`. If an active appointment already exists, the transaction is rejected.",
         "Booking karte waqt database check karta hai ki kya ye time slot pehle se kisi ne le rakha hai. Agar haan, toh system doosre mareez ko mana kar deta hai."),

        ("Q7. What happens when an appointment is cancelled?",
         "The appointment status transitions to 'Cancelled'. The system triggers `add_notification()` for both patient and doctor. The live queue immediately recalculates the remaining waiting count and reduces the estimated wait time for upcoming patients.",
         "Status 'Cancelled' ho jata hai, notification chala jata hai, aur line me peeche khade mareezon ka number apne aap aage khisak jata hai."),

        ("Q8. What are the appointment lifecycle states?",
         "The lifecycle has 4 core states: <b>Waiting</b> (booked, in queue) ➔ <b>Serving</b> (doctor called the token into cabin) ➔ <b>Completed</b> (consultation finished) or <b>Cancelled</b> (cancelled before checkup).",
         "Pehle mareez 'Waiting' me hota hai, doctor bulata hai toh 'Serving' ho jata hai, aur checkup ke baad 'Completed' ho jata hai."),

        ("Q9. What if a patient does not show up when called?",
         "The doctor's queue desk features a 'Skip / No-Show' action. This marks the appointment as skipped and enables calling the next queued token immediately, ensuring consultation flow is not blocked.",
         "Agar bulane par mareez nahi aata, toh doctor 'Skip' button dabakar agle mareez ko bula leta hai taaki line na ruke."),

        ("Q10. How is Role-Based Authorization (RBAC) implemented?",
         "Upon login, PHP initializes session variables (`$_SESSION['user_id']`, `$_SESSION['role']`). Every protected endpoint calls `require_role('admin')` or `require_role('doctor')`. Unauthorized requests are redirected immediately to `login.php`.",
         "Server session me role save karta hai. Agar mareez admin ka page kholne ki koshish karega toh system use turant bahar nikaal kar login page par bhej dega."),

        ("Q11. Why Vanilla CSS instead of Bootstrap or Tailwind?",
         "To maintain full layout control, eliminate third-party framework bloat, minimize bundle size, and build an ultra-fast, high-contrast healthcare design system tailored for responsive mobile viewports.",
         "Pure CSS se website bahut fast khulti hai, koi faltu code nahi hota, aur mobile par sabse accha view milta hai."),

        ("Q12. How does the Doctor Desk operate?",
         "Doctors log into their portal and open 'Live Queue Desk'. It displays today's patients in token sequence. Single-clicking 'Call Next' updates the current token to Serving. Once done, clicking 'Complete' finishes the visit and enables the next call.",
         "Doctor cabin me baithkar bas 'Call Next' dabata hai jisse agle mareez ka number display board par aa jata hai aur checkup ke baad 'Complete' kar deta hai."),

        ("Q13. How did you deploy to Railway Cloud?",
         "We packaged the application into a Docker container based on `php:8.2-apache` with `pdo_mysql` and `mpm_prefork`. A startup script `docker-entrypoint.sh` dynamically binds Apache to Railway's `$PORT`, connecting to Railway's managed cloud MySQL database.",
         "Docker aur Apache ka use karke humne code ko Railway Cloud par host kiya hai jisse website 24 ghante internet par live chalti hai."),

        ("Q14. What are the key tables in your database?",
         "Five relational tables in 3NF: `patients` (patient records), `doctors` (doctor profiles & schedules), `appointments` (junction table for bookings & tokens), `admins` (clinic management credentials), and `notifications` (queue event alerts).",
         "Panch tables hain: patients (mareez), doctors (chikitsak), appointments (bookings & tokens), admins (manager), aur notifications (alerts)."),

        ("Q15. What are the future enhancements for MediToken?",
         "1) SMS / WhatsApp API gateway (Twilio/Fast2SMS) to alert patients when their turn is 2 tokens away, 2) Payment Gateway integration (Razorpay/Stripe) for online OPD fee collection, and 3) An AI-driven queue predictor that calculates dynamic wait times based on doctor consultation speed.",
         "Future me WhatsApp par token aane ka alert bhejna, online consultation fees jama karwana, aur AI se bilkul accurate wait time batana shamil hai.")
    ]

    for q, a_eng, a_hin in qa_list:
        elements.append(Paragraph(f"<b>{q}</b>", q_style))
        elements.append(Paragraph(f"<b>English Answer:</b> {a_eng}", ans_style))
        elements.append(Paragraph(f"<b>Hindi Meaning (Seedha Matlab):</b> {a_hin}", hindi_meaning_style))
        elements.append(Spacer(1, 2))
        
    doc.build(elements, canvasmaker=NumberedCanvas)
    print(f"Successfully generated {filename}")

if __name__ == "__main__":
    build_pdf("d:/6486/MediToken_A_to_Z_Complete_Guide.pdf")
