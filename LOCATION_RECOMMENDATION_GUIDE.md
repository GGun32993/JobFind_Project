# Location-Based Job Recommendation System Implementation

## Overview
A comprehensive location-based job recommendation system that matches freelancers with jobs based on geographic proximity and location matching. Jobs are recommended on the freelancer dashboard showing match scores and distances.

---

## Features Implemented

### 1. **Database Modifications**
Added address fields to support detailed location information:

#### Tables Modified:
- **`freelancer_profile`**: Added columns for address details
  - `address` (VARCHAR 255) - Street address
  - `province` (VARCHAR 100) - Province/State
  - `district` (VARCHAR 100) - District/City
  - `postal_code` (VARCHAR 10) - Postal code
  - `latitude` (DOUBLE) - Geographic latitude
  - `longitude` (DOUBLE) - Geographic longitude

- **`employer_profile`**: Added columns for company address
  - `address` (VARCHAR 255) - Company street address
  - `province` (VARCHAR 100) - Company province/state
  - `district` (VARCHAR 100) - Company district
  - `postal_code` (VARCHAR 10) - Company postal code
  - `latitude` (DOUBLE) - Company geographic latitude
  - `longitude` (DOUBLE) - Company geographic longitude

#### Indexes Added:
- `job` table: `idx_location`, `idx_status`, `idx_created`
- `freelancer_profile` table: `idx_province`, `idx_location_field`, `idx_user`
- `employer_profile` table: `idx_province_emp`, `idx_user_emp`

### 2. **Location Helper Functions** (`location_helpers.php`)
New utility file with reusable location matching functions:

#### Key Functions:

**`haversineDistance($lat1, $lon1, $lat2, $lon2)`**
- Calculates great-circle distance between two geographic coordinates
- Returns distance in kilometers
- Uses Haversine formula for accurate geographic calculations

**`locationMatch($freelancer_location, $job_location)`**
- Performs text-based location matching
- Returns matching score (0-100)
- Scoring:
  - Exact match: 100%
  - Partial match: 80%
  - Common components match: 60%+

**`getRecommendedJobs($conn, $user_id, $limit = 10, $min_match_score = 30)`**
- Main recommendation function for freelancers
- Combines multiple matching strategies:
  - Text-based location matching
  - Province matching (70% score)
  - Distance-based matching (Haversine):
    - ≤5 km: 95% score
    - ≤15 km: 80% score
    - ≤30 km: 60% score
- Returns array of recommended jobs with match scores
- Default: returns top 10 jobs with minimum 30% match score
- Results sorted by: match score DESC, then by creation date DESC

**`getNearbyFreelancers($conn, $employer_user_id, $limit = 10, $min_match_score = 40)`**
- Recommendation function for employers
- Finds nearby freelancers by location
- Uses same matching strategies as job recommendations

### 3. **Freelancer Dashboard Updates** (`freelancer_dashboard.php`)
Enhanced recommended jobs section with:

#### Improvements:
- Integrated location-based recommendation system
- Displays match quality badges:
  - "Perfect Match ✨" (95%+ score) - Green
  - "Great Match ⭐" (85%+ score) - Amber
  - "Good Match" (70%+ score) - Blue
- Shows distance in kilometers (if coordinates available)
- Displays company name from employer profile
- Match percentage indicator
- Sorted by relevance (match score)

#### Data Displayed:
```
Job Title
├── Location: [Job location]
├── Salary: [Amount in THB]
├── Company: [Employer name]
├── Posted: [Date]
├── Distance: [Distance in km]
└── Match Badge: [Perfect/Great/Good Match] (XX%)
```

### 4. **Freelancer Profile Enhancements** (`my_profile.php`)
Added detailed address capture form:

#### New Fields:
- **Location** - General area (existing, kept for compatibility)
- **Address** - Detailed street address (textarea)
- **Province** - State/Province name
- **District** - District/City name
- **Postal Code** - ZIP/postal code

#### Benefits:
- Freelancers can specify exact location for accurate matching
- Better geographic precision for distance calculations
- Forms organized under "ที่อยู่โดยละเอียด" (Detailed Address) section

### 5. **Employer Profile Enhancements** (`employer_profile.php`)
Added company address capture form:

#### New Fields:
- **Address** - Company street address (textarea)
- **Province** - Company state/province
- **District** - Company district
- **Postal Code** - Company postal code

#### Benefits:
- Employers can specify company location
- Jobs automatically associated with company location
- Enables distance-based matching for employers

---

## How It Works

### For Freelancers:

1. **Profile Setup**
   - Freelancer fills in "ที่อยู่โดยละเอียด" (Detailed Address) section
   - Captures: Address, Province, District, Postal Code
   - Optional: Coordinates (latitude/longitude) if available

2. **Job Recommendations**
   - System queries all open, approved jobs from last 30 days
   - For each job, calculates match score based on:
     - Text similarity with freelancer's location
     - Province matching
     - Geographic distance (if coordinates available)
   - Returns top 10 most relevant jobs
   - Displays with match percentage and distance

3. **Dashboard Display**
   - Recommended jobs shown in dedicated section
   - Jobs sorted by match score (highest first)
   - Visual badges indicate match quality
   - One-click "Apply" button

### For Employers:

1. **Profile Setup**
   - Employer fills company address information
   - Enables better freelancer matching

2. **Future Feature** (Ready to implement)
   - Nearby freelancer recommendations
   - Uses `getNearbyFreelancers()` function
   - Can help employers find relevant talent

---

## Matching Algorithm Details

### Scoring Criteria:

#### 1. Text-Based Location Matching (40% weight)
```
- Exact match: 100%
- "Bangkok" contains "Bangkok": 80%
- Common words found: 40-70%
```

#### 2. Province Matching (30% weight)
```
If freelancer province = job employer province: +70% bonus
```

#### 3. Geographic Distance (30% weight) *if coordinates available*
```
Distance ≤ 5 km    → 95% score
Distance ≤ 15 km   → 80% score
Distance ≤ 30 km   → 60% score
Distance > 30 km   → 0% (excluded unless text match)
```

### Final Score Calculation:
```
Final Score = max(text_match, province_match, distance_match)
```

### Filtering:
- Minimum 30% match score required (default)
- Only includes open jobs with approved status
- Jobs must be from last 30 days
- Excludes closed/completed jobs

---

## Database Setup Instructions

### Step 1: Apply Migration
Run the SQL migration file to add address fields:

```bash
mysql -u root jobfind < migrations/add_address_fields.sql
```

Or run SQL queries in phpMyAdmin:
```sql
-- See migrations/add_address_fields.sql for full SQL
```

### Step 2: Verify Fields Added
```sql
DESCRIBE freelancer_profile;  -- Should show: address, province, district, postal_code, latitude, longitude
DESCRIBE employer_profile;     -- Should show: address, province, district, postal_code, latitude, longitude
```

---

## File Structure

```
JobFind_Project/
├── location_helpers.php              # Location matching functions
├── migrations/
│   └── add_address_fields.sql       # Database migration
├── freelancer_dashboard.php          # Updated with recommendations
├── my_profile.php                    # Updated freelancer profile form
├── employer_profile.php              # Updated employer profile form
└── [other files unchanged]
```

---

## Testing Guide

### Test Scenario 1: Create Freelancer Profile
1. Login as freelancer
2. Go to My Profile
3. Fill in:
   - Location: "Bangkok"
   - Address: "123 Sukhumvit Soi 5"
   - Province: "Bangkok"
   - District: "Watthana"
   - Postal Code: "10110"
4. Save profile

### Test Scenario 2: Create Job with Employer Location
1. Login as employer
2. Update Profile:
   - Fill company address details
   - Province: "Bangkok"
   - District: "Pathumwan"
3. Create/post job with location in Bangkok
4. Job appears on freelancer dashboard

### Test Scenario 3: Verify Recommendations
1. Login as freelancer from Test Scenario 1
2. Visit Dashboard
3. Check "งานที่แนะนำสำหรับคุณ" section
4. Should see jobs from Bangkok area
5. Verify match badges display correctly
6. Distance should show (if coordinates available)

---

## Performance Optimization

### Indexes for Speed
- `job(location)` - For location-based filtering
- `job(status, admin_status)` - For job status filtering
- `freelancer_profile(province, location)` - For freelancer lookup
- `employer_profile(province)` - For employer lookup

### Query Optimization
- Fetches max 50 jobs, then filters in PHP (reduces memory)
- Returns top 10 after scoring (pagination ready)
- Uses simple text matching before distance calculations
- Geographic distance only calculated when needed

---

## Future Enhancements

### Planned Features:

1. **Employer Nearby Freelancer Recommendations**
   - Use `getNearbyFreelancers()` on employer dashboard
   - Show nearby available freelancers

2. **Saved Job Preferences**
   - Let freelancers set preferred locations
   - Personalized recommendations

3. **Coordinate-Based Search**
   - Map interface for location selection
   - Automatic latitude/longitude capture
   - More accurate distance calculations

4. **Notification System**
   - Notify freelancers of nearby jobs
   - Email alerts for high-match jobs

5. **Admin Location Management**
   - Bulk upload coordinates for locations
   - Location/Province mapping database

6. **Analytics Dashboard**
   - Track recommendation success rate
   - Show most popular job locations
   - Recommend areas for job posting

---

## Troubleshooting

### Jobs Not Showing in Recommendations?
**Check:**
1. Freelancer profile has location filled in
2. Jobs are marked as `status='open'` and `admin_status='approved'`
3. Jobs are from last 30 days
4. Match score ≥ 30% (check location similarity)

**Solution:**
- Update freelancer profile with accurate location
- Check job status in admin dashboard
- Verify employer added job location

### Distance Always Shows as NULL?
**Reason:** Coordinates (latitude/longitude) not set

**Solution (Optional):**
- Manually update coordinates in database:
  ```sql
  UPDATE freelancer_profile SET latitude=13.7563, longitude=100.5018 WHERE user_id=X;
  UPDATE employer_profile SET latitude=13.7563, longitude=100.5018 WHERE user_id=X;
  ```
- Or implement map-based coordinate capture in future

### Performance Issues?
**Optimize:**
1. Increase job search limit from 50 to higher value cautiously
2. Add caching for frequently accessed data
3. Use pagination for large datasets
4. Consider cronjob for pre-calculated scores

---

## Code Examples

### Get Recommendations in Custom Code:
```php
<?php
include("location_helpers.php");

$recommended = getRecommendedJobs($conn, $user_id, limit: 15, min_match_score: 40);

foreach ($recommended as $job) {
    echo "{$job['title']} - Match: {$job['match_score']}% - Distance: {$job['distance_km']} km";
}
?>
```

### Calculate Distance Between Two Points:
```php
<?php
include("location_helpers.php");

$distance = haversineDistance(13.7563, 100.5018, 14.0695, 100.7399);
echo "Distance: {$distance} km";
?>
```

### Custom Location Matching:
```php
<?php
include("location_helpers.php");

$score = locationMatch("Bangkok", "Bangkok, Watthana");
echo "Match Score: {$score}%";
?>
```

---

## Support & Maintenance

### Regular Tasks:
1. Monitor recommendation quality via analytics
2. Update location databases with new areas
3. Fine-tune match score thresholds based on user feedback
4. Maintain coordinate database for popular locations

### Contact:
For questions or improvements, refer to the location_helpers.php file structure and customize as needed.

---

**Version:** 1.0  
**Last Updated:** May 25, 2026  
**Status:** Production Ready
