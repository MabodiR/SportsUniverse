# SportsUniverse Web Application - Codex Build Brief

## 1. Product summary
SportsUniverse is a sports-focused social ecosystem that connects athletes, coaches, scouts, clubs, sponsors, businesses and fans. The web application should feel like a sports version of a short-video platform: users scroll through athlete videos, interact with posts, follow profiles, and request messages, while admin users can manage content, users and moderation.

## 2. Core product direction
Build a web application with a dark, modern, video-first interface. The main user experience should be a TikTok-style sports feed, but with professional sports profile details, discovery filters and admin controls.

Primary goals:
- Help athletes showcase talent through highlight videos.
- Help scouts, clubs and fans discover athletes by sport, position, age and location.
- Enable community engagement through follow, like, comment and share.
- Allow message requests when two users are not connected.
- Support admin moderation and platform management.

## 3. Brand direction
Use the existing SportsUniverse design direction:
- Deep Navy / almost black background: #050816 or #07070A
- Universe Blue: #2563EB
- Community Pink: #FF2D55
- Opportunity Orange: #F97316
- Growth Green: #22C55E
- Text Primary: #FFFFFF
- Text Secondary: #A1A1AA
- Surface Card: #111827 or #121212
- Border: #27272A

Typography:
- Use Inter, system-ui or a similar clean sans-serif font.
- Large bold headings.
- Clean readable body text.

Design style:
- Dark immersive background.
- Rounded cards and video containers.
- TikTok-inspired left sidebar and right action rail.
- SportsUniverse branding, not a copy of TikTok.
- Clear admin-friendly dashboards for management pages.

## 4. User roles
Support role-based onboarding and navigation for:
- Athlete
- Fan
- Coach
- Scout / Agent
- Club / Academy
- Business / Sponsor
- Admin

## 5. Main navigation
Left sidebar items:
- For You
- Explore
- Following
- Live
- Upload
- Messages
- Profile
- Opportunities
- Admin
- More

Top controls:
- Search bar: Search athlete, sport, position, location
- Category tabs: For You, Following, Football, Rugby, Sprinting, Netball, Women in Sports
- Login / profile menu

## 6. Required pages and routes

### 6.1 Login page
Route: `/login`

Purpose:
Allow returning users to access the platform.

Elements:
- SportsUniverse logo
- Email or phone input
- Password input
- Login button
- Forgot password link
- Phone OTP login option
- Social login buttons: Continue with Google, Continue with Apple
- Link to registration page
- Small right-side preview panel showing sports video feed cards

Validation:
- Required email/phone
- Required password when using password login
- OTP flow placeholder for phone login

### 6.2 Multi-step registration flow
Base route: `/register`

Registration must allow users to skip some profile information and complete it later. Show profile completeness at the end, for example 70% if some fields are missing.

#### Step 1: Account basics
Route: `/register/account`
Fields:
- Full name
- Email address
- Phone number
- Password
- Confirm password
- Social registration buttons: Google, Apple
Actions:
- Continue
- Already have an account? Login

#### Step 2: Select role
Route: `/register/role`
Cards:
- Athlete
- Fan
- Coach
- Scout / Agent
- Club / Academy
- Business / Sponsor

Rules:
- Role selection is required.
- The rest of onboarding changes based on role.

#### Step 3A: Athlete sport details
Route: `/register/athlete-details`
Show only when role is Athlete.
Fields:
- Primary sport: Football, Rugby, Sprinting, Netball, Cricket, Other
- Position: Striker, Midfielder, Defender, Goalkeeper, Wing, Sprinter, Other
- Current club / school / academy
- Playing level: School, Amateur, Semi-professional, Professional
- Dominant foot / hand if relevant
- Short bio
Actions:
- Continue
- Skip for now

#### Step 3B: Fan interests
Route: `/register/fan-interests`
Show only when role is Fan.
Fields:
- Interested sports: Football, Rugby, Sprinting, Netball, Cricket, Women in Sports, Local Clubs
- Favourite teams or athletes
- Notification preferences
Actions:
- Continue
- Skip for now

#### Step 4: Age and location
Route: `/register/location`
Fields:
- Date of birth or age
- Country
- Province / state
- City / town / village
- Township / suburb
- Google-style location search field
- Enable current location button
Actions:
- Continue
- Skip for now

#### Step 5: Profile media
Route: `/register/media`
Fields:
- Profile photo upload
- Intro or highlight video upload, optional
- Gallery photos, optional
Actions:
- Complete registration
- Skip for now

#### Step 6: Profile completeness summary
Route: `/register/completeness`
Show:
- Profile completeness percentage, for example 70%
- Completed sections
- Missing sections
- Button: Complete profile now
- Button: Continue to feed

Completeness example logic:
- Account basics: 20%
- Role selected: 15%
- Sport/interests: 20%
- Age and location: 20%
- Profile photo/media: 15%
- Bio or extras: 10%

## 7. Video-first user pages

### 7.1 For You sports video feed
Route: `/feed`

Layout:
- Dark full-page layout.
- Fixed left navigation.
- Center vertical video feed.
- Each video post is large and dominant.
- Right action rail for interactions.
- Optional right detail panel for athlete profile summary.

Post card content:
- Athlete name
- Sport and position
- Location
- Video caption
- Hashtags
- Follow button
- Profile completeness / verified badge where relevant

Right action rail:
- Like count
- Comment count
- Share
- Save
- Follow
- Message Request

Message request rule:
- If users are not following each other, show Send Message Request.
- If already connected, show Message.

### 7.2 Video detail + comments + message request
Route: `/videos/:id`

Layout:
- Large video player on the left/center.
- Comment panel on the right.
- Athlete mini-profile card.
- Message Request button.
- Related videos below or in side panel.

Comment features:
- Add comment
- Like comment
- Reply to comment, optional
- Sort comments by top/newest

### 7.3 Athlete public profile
Route: `/athletes/:id`

Sections:
- Cover/profile header
- Profile photo
- Name
- Sport
- Position
- Age
- Location
- Club/school/academy
- Follow button
- Message Request button
- Profile completeness badge, if owner/admin view
- Tabs: Videos, Stats, Achievements, About, Opportunities

Video grid:
- Highlight videos
- Training clips
- Match clips

### 7.4 Following feed
Route: `/following`

Purpose:
Show posts only from athletes, clubs and profiles the user follows.

Elements:
- Same video feed layout as For You
- Empty state if user follows nobody
- Suggested athletes to follow

### 7.5 Message requests / inbox
Route: `/messages`

Sections:
- Inbox
- Message Requests
- Archived

Message request card:
- Sender profile
- Sport/role
- Reason or first message preview
- Accept button
- Decline button
- View profile button

### 7.6 Upload athlete video flow
Route: `/upload`

Steps:
1. Upload video
2. Add video details
3. Tag sport, position and location
4. Select visibility
5. Publish

Fields:
- Video file upload
- Caption
- Sport type
- Position
- Location
- Tags
- Visibility: Public, Followers only, Scouts/Clubs only
- Allow comments toggle
- Allow message requests toggle

### 7.7 Admin moderation dashboard
Route: `/admin`

Purpose:
Allow admin users to manage the sports community safely and professionally.

Dashboard cards:
- Total users
- New athletes
- Videos uploaded
- Reported posts
- Pending verifications
- Message request reports

Tables:
- Reported content
- Pending athlete verification
- Flagged comments
- New user registrations

Actions:
- Approve
- Reject
- Suspend user
- Remove content
- Mark as reviewed
- View profile

## 8. Components to build
Suggested React components:
- AppLayout
- Sidebar
- TopSearchBar
- CategoryTabs
- VideoFeed
- VideoPostCard
- VideoActionRail
- AthleteMiniCard
- CommentPanel
- MessageRequestButton
- ProfileHeader
- ProfileCompletenessBar
- RegistrationLayout
- RegistrationStepIndicator
- SocialAuthButtons
- RoleSelectionCard
- LocationSearchInput
- UploadVideoWizard
- AdminStatsCard
- AdminModerationTable
- InboxList
- MessageRequestCard

## 9. Suggested data models

### User
- id
- fullName
- email
- phone
- role
- avatarUrl
- location
- isVerified
- profileCompleteness
- createdAt

### AthleteProfile
- id
- userId
- sport
- position
- age
- clubOrSchool
- level
- bio
- stats
- achievements

### VideoPost
- id
- userId
- videoUrl
- thumbnailUrl
- caption
- sport
- position
- location
- tags
- likesCount
- commentsCount
- sharesCount
- visibility
- createdAt

### Comment
- id
- videoPostId
- userId
- text
- likesCount
- createdAt

### Follow
- followerId
- followingId
- createdAt

### MessageRequest
- id
- senderId
- receiverId
- status: pending, accepted, declined
- message
- createdAt

### Opportunity
- id
- organisationId
- title
- type: trial, job, training camp
- location
- deadline
- requirements
- createdAt

### Report
- id
- reporterId
- targetType: video, comment, user, message
- targetId
- reason
- status: pending, reviewed, actioned
- createdAt

## 10. Suggested frontend stack
- React or Next.js
- TypeScript
- Tailwind CSS
- React Router or Next.js App Router
- Zustand or Redux Toolkit for state management
- React Hook Form + Zod for forms
- Video player using native HTML5 video or a React video library

## 11. Development instructions for Codex
Build the application from this specification.

Start with:
1. Create project structure.
2. Create theme tokens using the SportsUniverse colors.
3. Build reusable layout components.
4. Build authentication pages.
5. Build multi-step registration with skip support and profile completeness calculation.
6. Build TikTok-style sports video feed.
7. Build video detail and comments page.
8. Build athlete public profile.
9. Build following feed.
10. Build messages and message requests.
11. Build upload video wizard.
12. Build admin moderation dashboard.

Use mock data first. Keep code clean and component-based. Make sure the UI is responsive for desktop and tablet, with future mobile adaptation.

## 12. Acceptance criteria
- User can register using email/password or social auth UI placeholders.
- User can select role during registration.
- Athlete onboarding asks for sport, position, age and location.
- Fan onboarding asks for sports interests.
- User can skip optional fields.
- Profile completeness percentage updates based on missing information.
- Feed displays athlete videos in a TikTok-style layout.
- Users can like, comment, share, save and follow.
- Message Request appears when users are not connected.
- Admin page shows moderation stats and tables.
- Upload page supports video metadata and visibility controls.
- Dark theme matches SportsUniverse brand.
