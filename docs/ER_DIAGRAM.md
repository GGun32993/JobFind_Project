# JobFind ER Diagram

Chen-style version with rectangles, ovals, diamonds, and cardinality labels:
[`ER_CHEN_DIAGRAM.md`](ER_CHEN_DIAGRAM.md)

Generated from:

- `database/jobfind.sql`
- `migrations/*.sql`
- runtime schema helpers in `helpers/*_schema.php`, `helpers/category_helpers.php`, and `helpers/job_image_helpers.php`
- PHP query usage across `admin/`, `employer/`, `freelancer/`, `actions/`, `support/`, `helpers/`, and `services/`

Note: several columns named `employer_id` or `freelancer_id` store `Users.user_id` directly. The database dump only declares some foreign keys; this diagram includes both declared database relationships and logical relationships used by the application code.

```mermaid
erDiagram
    Users {
        int user_id PK
        varchar username
        varchar email
        varchar password
        varchar fullname
        varchar phone
        varchar gender
        enum role
        double latitude
        double longitude
        varchar profile_image
        text company_details
        timestamp created_at
    }

    Employer_Profile {
        int employer_id PK
        int user_id FK
        varchar employer_name
        text employer_description
        varchar address
        varchar province
        varchar district
        varchar postal_code
        double latitude
        double longitude
        double preferred_radius_km
        int like_count
        timestamp created_at
    }

    Freelancer_Profile {
        int freelancer_id PK
        int user_id FK
        text skill
        text experience
        int age
        varchar location
        varchar address
        varchar province
        varchar district
        varchar postal_code
        double latitude
        double longitude
        double preferred_radius_km
        float rating
        timestamp created_at
    }

    Job {
        int job_id PK
        int employer_id FK
        varchar title
        text description
        varchar location
        decimal salary
        double latitude
        double longitude
        datetime deadline
        enum status
        enum admin_status
        varchar category
        varchar job_subcategory
        varchar employment_type
        varchar image_path
        timestamp created_at
        timestamp updated_at
    }

    Job_Images {
        int image_id PK
        int job_id FK
        varchar image_path
        int sort_order
        timestamp created_at
    }

    Job_Application {
        int application_id PK
        int job_id FK
        int freelancer_id FK
        timestamp apply_date
        varchar status
    }

    Resume {
        int resume_id PK
        int freelancer_id FK
        varchar file_name
        timestamp upload_date
    }

    Saved_Freelancers {
        int id PK
        int employer_id FK
        int freelancer_id FK
        timestamp saved_at
    }

    Like_Employer {
        int like_id PK
        int freelancer_id FK
        int employer_id FK
        timestamp created_at
    }

    Chat_Messages {
        int message_id PK
        int sender_id FK
        int receiver_id FK
        text message
        timestamp sent_at
        tinyint is_read
    }

    Employer_Review {
        int review_id PK
        int employer_id FK
        int freelancer_id FK
        int job_id FK
        int rating
        text comment
        timestamp created_at
    }

    Freelancer_Review {
        int review_id PK
        int freelancer_id FK
        int job_id FK
        int employer_id FK
        int rating
        text comment
        text review
        timestamp created_at
    }

    Employer_Rating {
        int rating_id PK
        int employer_id FK
        int freelancer_id FK
        int score
        timestamp created_at
    }

    Freelancer_Rating {
        int rating_id PK
        int freelancer_id FK
        int employer_id FK
        int score
        timestamp created_at
    }

    Categories {
        int category_id PK
        varchar name
        varchar icon
        text description
        timestamp created_at
    }

    Job_Subcategories {
        int subcategory_id PK
        int category_id FK
        varchar name
        timestamp created_at
    }

    Category_Seed_Runs {
        varchar seed_key PK
        timestamp applied_at
    }

    Users ||--o| Employer_Profile : has
    Users ||--o| Freelancer_Profile : has
    Users ||--o{ Job : posts
    Job ||--o{ Job_Images : has
    Job ||--o{ Job_Application : receives
    Users ||--o{ Job_Application : applies
    Users ||--o{ Resume : uploads
    Users ||--o{ Saved_Freelancers : saves
    Users ||--o{ Saved_Freelancers : is_saved
    Users ||--o{ Like_Employer : likes
    Users ||--o{ Like_Employer : is_liked
    Users ||--o{ Chat_Messages : sends
    Users ||--o{ Chat_Messages : receives
    Users ||--o{ Employer_Review : receives_review
    Users ||--o{ Employer_Review : writes_review
    Job |o--o{ Employer_Review : reviewed_for
    Users ||--o{ Freelancer_Review : receives_review
    Users ||--o{ Freelancer_Review : writes_review
    Job |o--o{ Freelancer_Review : reviewed_for
    Users ||--o{ Employer_Rating : receives_rating
    Users ||--o{ Employer_Rating : gives_rating
    Users ||--o{ Freelancer_Rating : receives_rating
    Users ||--o{ Freelancer_Rating : gives_rating
    Categories ||--o{ Job_Subcategories : contains
    Categories |o--o{ Job : category_name
    Job_Subcategories |o--o{ Job : subcategory_name
```

## Relationship Notes

- Declared database FKs in `database/jobfind.sql`:
  - `Job.employer_id -> Users.user_id`
  - `Job_Images.job_id -> Job.job_id`
  - `Employer_Review.job_id -> Job.job_id`
  - `Saved_Freelancers.employer_id -> Users.user_id`
  - `Saved_Freelancers.freelancer_id -> Users.user_id`
- Logical relationships used by the PHP code but not fully enforced by FK constraints:
  - `Employer_Profile.user_id -> Users.user_id`
  - `Freelancer_Profile.user_id -> Users.user_id`
  - `Job_Application.job_id -> Job.job_id`
  - `Job_Application.freelancer_id -> Users.user_id`
  - `Resume.freelancer_id -> Users.user_id`
  - `Like_Employer.employer_id -> Users.user_id`
  - `Like_Employer.freelancer_id -> Users.user_id`
  - `Chat_Messages.sender_id -> Users.user_id`
  - `Chat_Messages.receiver_id -> Users.user_id`
  - `Employer_Review.employer_id -> Users.user_id`
  - `Employer_Review.freelancer_id -> Users.user_id`
  - `Freelancer_Review.employer_id -> Users.user_id`
  - `Freelancer_Review.freelancer_id -> Users.user_id`
  - `Freelancer_Review.job_id -> Job.job_id`
  - `Job_Subcategories.category_id -> Categories.category_id`
  - `Job.category -> Categories.name`
  - `Job.job_subcategory -> Job_Subcategories.name`
