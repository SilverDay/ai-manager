# New Wizard Flow

Scaffolds a multi-step wizard (registration, classification, risk assessment, FRIA).

## Usage

Invoke when asked to create or extend a wizard workflow.

## Steps

1. **Read the spec** for the wizard's steps, fields, and logic (Sections 7.4, 7.5, 7.6, 7.8).

2. **Create the Pinia store** in `frontend/src/stores/{wizard}Wizard.js`:
   - State: `currentStep`, `totalSteps`, `stepData` (object per step), `isSubmitting`, `errors`.
   - Actions: `nextStep()`, `prevStep()`, `goToStep(n)`, `validateCurrentStep()`, `submitWizard()`, `resetWizard()`.
   - Persist to `sessionStorage` so refresh doesn't lose progress.
   - On submit: call the appropriate API endpoint, handle response.

3. **Create the shell view** in `frontend/src/views/{Wizard}Wizard.vue`:
   - Uses `<WizardShell>` component (provides progress bar, navigation buttons, help panel).
   - Dynamically renders the current step component.
   - Props to WizardShell: `steps` array (label + component), `currentStep`, `canProceed`.

4. **Create step components** in `frontend/src/views/{wizard}/`:
   - One component per step: `Step1Basic.vue`, `Step2AiDetermination.vue`, etc.
   - Each step emits `valid` / `invalid` to control the Next button.
   - Each step reads/writes its data slice from the wizard store.
   - Each step includes contextual help text (right panel content).

5. **Help content**: each step defines:
   - Question explanations (what this field means).
   - "Why are we asking this?" text.
   - Examples.
   - Glossary term links.
   - All text uses i18n keys.

6. **Backend handling**: the wizard submits as a single POST to the backend. The backend Service class:
   - Validates the complete wizard payload.
   - Creates the main record (e.g., `ai_systems` row).
   - Creates related records (classification, initial risk scores).
   - Triggers the approval workflow if applicable.
   - Returns: created resource, classification result, required actions, next review date.

7. **Add routes** for the wizard view and register them in the router.

8. **Test**: store unit tests (step transitions, validation), backend integration tests (wizard submission endpoint).
