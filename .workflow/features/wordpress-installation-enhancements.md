# Feature: WordPress Installation Enhancements

**Issue:** [To be determined]
**Type:** feature
**Status:** planned
**Created:** 2026-03-10

## Overview

Enhance WordPress installation in Tuti CLI to support multiple installation types (Regular, Bedrock, Multisite with subdomain/subdirectory modes), add reinstallation capability via `--force` flag, and provide interactive by default with CLI flags for automation. The feature adds flexibility for different WordPress development workflows while maintaining backward compatibility with existing single-site standard installations.

## Acceptance Criteria

- [ ] Support three WordPress installation types: Regular (standard), Bedrock, and Multisite
- [ ] Multisite supports both subdomain and subdirectory modes
- [ ] Interactive mode is the default behavior for `wp:setup` command
- [ ] `--no-interactive` flag enables automation with sensible defaults
- [ ] `--type` flag allows specifying installation type (standard|bedrock)
- [ ] `--multisite` flag enables multisite with optional mode (subdomain|subdirectory)
- [ ] `--force` flag enables reinstallation even if WordPress is already installed
- [ ] Configuration stored in `.tuti/config.json` (settings) and `.env` (credentials)
- [ ] All new code follows project PHP standards (strict types, final classes, constructor injection)
- [ ] All external process execution uses array syntax (no shell injection risk)
- [ ] Comprehensive test coverage for all new functionality
- [ ] Documentation updated to reflect new features

## Architecture Impact

### New Components

1. **Enums** (`app/Enums/`)
   - `WordPressType` - SINGLE, BEDROCK
   - `MultisiteMode` - NONE, SUBDOMAIN, SUBDIRECTORY

2. **Services** (`app/Services/WordPress/`)
   - `WordPressSetupService` - Shared setup logic for all installation types

3. **Command Changes**
   - `WpSetupCommand` - Add new flags: `--type`, `--multisite`, `--no-interactive`

4. **Stub Files** (`stubs/stacks/wordpress/`)
   - `configs/multisite/` - Multisite configuration templates
   - `configs/bedrock/` - Bedrock-specific configurations

### Modified Components

1. **WordPressStackInstaller** - Extend to support multisite detection and configuration
2. **WordPressEnvHandler** - Add multisite environment variable handling
3. **BedrockEnvHandler** - Add multisite support for Bedrock installations

### Data Flow

```
User Input (wp:setup command)
    ↓
Interactive Prompts or CLI Flags
    ↓
WordPressSetupService
    ├── Detect Installation Type
    ├── Gather Configuration
    ├── Validate Inputs
    └── Execute Installation
        ├── Standard WP (wp-cli core install)
        ├── Bedrock (composer create-project + wp-cli)
        └── Multisite (wp-cli core multisite-convert)
    ↓
Update Config Files
    ├── .tuti/config.json (settings)
    └── .env (credentials)
```

## Atomic Task Breakdown

### Phase 1: Foundation & Enums

#### Task 1.1: Create WordPressType Enum
**Agent:** php-pro
**Input:** None
**Output:** `app/Enums/WordPressType.php` enum file
**Time:** 15min

**Steps:**
1. Create `app/Enums/WordPressType.php` with string-backed enum
2. Define cases: SINGLE, BEDROCK with descriptive values
3. Add comprehensive docblock explaining each case
4. Follow existing enum pattern from `app/Enums/ContainerNamingEnum.php`

**Completion Criteria:**
- [ ] Enum created at `app/Enums/WordPressType.php`
- [ ] Has string-backed cases: SINGLE='single', BEDROCK='bedrock'
- [ ] Follows project enum pattern (final, string-backed, comprehensive docblock)
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Create: `app/Enums/WordPressType.php`

**Validation:**
- `docker compose exec -T app composer test:types` passes
- Enum can be instantiated with expected values
- Enum serialization works correctly

---

#### Task 1.2: Create MultisiteMode Enum
**Agent:** php-pro
**Input:** None
**Output:** `app/Enums/MultisiteMode.php` enum file
**Time:** 15min

**Steps:**
1. Create `app/Enums/MultisiteMode.php` with string-backed enum
2. Define cases: NONE='none', SUBDOMAIN='subdomain', SUBDIRECTORY='subdirectory'
3. Add comprehensive docblock explaining each case and usage
4. Follow existing enum pattern from `app/Enums/ContainerNamingEnum.php`

**Completion Criteria:**
- [ ] Enum created at `app/Enums/MultisiteMode.php`
- [ ] Has string-backed cases: NONE='none', SUBDOMAIN='subdomain', SUBDIRECTORY='subdirectory'
- [ ] Follows project enum pattern (final, string-backed, comprehensive docblock)
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Create: `app/Enums/MultisiteMode.php`

**Validation:**
- `docker compose exec -T app composer test:types` passes
- Enum can be instantiated with expected values
- Enum serialization works correctly

---

### Phase 2: Core Service Implementation

#### Task 2.1: Create WordPressSetupService Structure
**Agent:** php-pro
**Input:** Task 1.1, Task 1.2 completed
**Output:** `app/Services/WordPress/WordPressSetupService.php` basic structure
**Time:** 30min

**Steps:**
1. Create `app/Services/WordPress/WordPressSetupService.php` directory and file
2. Define class as `final readonly` with constructor injection
3. Import required dependencies: DockerExecutorInterface, WordPressStackInstaller, JsonFileService
4. Define basic class structure with constructor and docblock
5. Register service in `app/Providers/AppServiceProvider.php`

**Completion Criteria:**
- [ ] Service created at `app/Services/WordPress/WordPressSetupService.php`
- [ ] Class is `final readonly` with proper constructor injection
- [ ] Service registered in `app/Providers/AppServiceProvider.php`
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Create: `app/Services/WordPress/WordPressSetupService.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Validation:**
- Service can be resolved from container
- Constructor dependency injection works
- No missing dependencies or imports

---

#### Task 2.2: Implement Installation Type Detection
**Agent:** php-pro
**Input:** Task 2.1 completed
**Output:** WordPressSetupService with detection methods
**Time:** 30min

**Steps:**
1. Implement `detectInstallationType()` method that returns WordPressType enum
2. Use existing `detectInstallationType()` logic from WordPressStackInstaller
3. Convert string return type to WordPressType enum
4. Implement `detectMultisiteMode()` method that returns MultisiteMode enum
5. Add logic to check wp-config.php for MULTISITE constant
6. Add comprehensive docblocks for all methods

**Completion Criteria:**
- [ ] `detectInstallationType()` method returns WordPressType enum
- [ ] `detectMultisiteMode()` method returns MultisiteMode enum
- [ ] Method logic matches existing WordPressStackInstaller detection
- [ ] All methods have proper type hints and return types
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Services/WordPress/WordPressSetupService.php`

**Validation:**
- Detection methods return correct enum values
- Logic handles different WordPress project structures
- Type hints are correct and enforced

---

#### Task 2.3: Implement Configuration Validation
**Agent:** php-pro
**Input:** Task 2.2 completed
**Output:** WordPressSetupService with validation methods
**Time:** 30min

**Steps:**
1. Implement `validateConfiguration()` method for input validation
2. Validate flag combinations: `--type=bedrock` cannot be used with `--multisite`
3. Validate required inputs for each installation type
4. Use Laravel validation patterns with clear error messages
5. Add method to validate environment variables
6. Test validation logic with various input combinations

**Completion Criteria:**
- [ ] `validateConfiguration()` method validates all input combinations
- [ ] Proper validation error messages for invalid flag combinations
- [ ] Validation handles all installation type and multisite mode combinations
- [ ] Method returns boolean or throws appropriate exceptions
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Services/WordPress/WordPressSetupService.php`

**Validation:**
- Validation passes for valid inputs
- Validation fails for invalid combinations with clear messages
- Error messages are user-friendly and actionable

---

#### Task 2.4: Implement Installation Methods
**Agent:** php-pro
**Input:** Task 2.3 completed
**Output:** WordPressSetupService with complete installation logic
**Time:** 60min

**Steps:**
1. Implement `configureStandardWordPress()` method for single-site setup
2. Implement `configureBedrock()` method for Bedrock setup
3. Implement `configureMultisite()` method for multisite setup
4. Use existing WP-CLI integration from WordPressStackInstaller
5. Add proper error handling and user feedback
6. Use array syntax for all Process executions
7. Ensure each method follows existing patterns from WordPressStackInstaller

**Completion Criteria:**
- [ ] `configureStandardWordPress()` method handles single-site setup
- [ ] `configureBedrock()` method handles Bedrock setup
- [ ] `configureMultisite()` method handles multisite setup
- [ ] All methods use array syntax for Process execution
- [ ] Proper error handling and user-friendly messages
- [ ] Methods delegate to appropriate WordPressStackInstaller methods
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Services/WordPress/WordPressSetupService.php`

**Validation:**
- Each installation method works correctly
- All Process executions use array syntax
- Error handling is comprehensive and user-friendly
- Integration with WordPressStackInstaller works properly

---

#### Task 2.5: Extend WordPressStackInstaller for Multisite
**Agent:** php-pro
**Input:** Task 2.4 completed
**Output:** WordPressStackInstaller with multisite support
**Time:** 60min

**Steps:**
1. Add `detectMultisiteMode()` method to detect existing multisite setup
2. Add `convertToMultisite()` method to enable multisite via WP-CLI
3. Add `addMultisiteSite()` method to create additional sites in network
4. Update `detectExistingProject()` to handle multisite detection
5. Use `wp core multisite-convert` command for conversion
6. Support both `--subdomains` and `--no-subdomains` flags
7. Use `wp site create` for adding sites to existing network
8. Detect multisite by checking `wp-config.php` for `MULTISITE` constant
9. Add proper docblocks and error handling

**Completion Criteria:**
- [ ] `detectMultisiteMode()` method detects existing multisite setup
- [ ] `convertToMultisite()` method enables multisite via WP-CLI
- [ ] `addMultisiteSite()` method creates additional sites in network
- [ ] All methods use array syntax for Process execution
- [ ] Proper error handling and user-friendly messages
- [ ] Updated docblocks with multisite examples
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Services/Stack/Installers/WordPressStackInstaller.php`

**Validation:**
- Multisite detection works correctly
- Conversion process works for both subdomain and subdirectory modes
- Site creation works in existing multisite networks
- All WP-CLI commands use array syntax
- Error handling is comprehensive

---

### Phase 3: Command Implementation

#### Task 3.1: Update WpSetupCommand Signature and Basic Structure
**Agent:** cli-developer
**Input:** Task 2.1-2.4 completed
**Output:** WpSetupCommand with new flags and basic structure
**Time:** 30min

**Steps:**
1. Update `WpSetupCommand` signature to add `--type`, `--multisite`, `--no-interactive` flags
2. Update `$description` to mention all installation types
3. Import new enums and WordPressSetupService
4. Add basic service injection for WordPressSetupService
5. Add basic validation of flag combinations
6. Update command help text to document new flags

**Completion Criteria:**
- [ ] `--type` flag added (values: standard|bedrock)
- [ ] `--multisite` flag added (values: none|subdomain|subdirectory)
- [ ] `--no-interactive` flag added (boolean, false by default)
- [ ] `--force` flag remains (for reinstallation)
- [ ] Command imports WordPressSetupService and new enums
- [ ] Basic validation of flag combinations implemented
- [ ] Updated command description and help text
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Commands/Stack/WpSetupCommand.php`

**Validation:**
- Command signature accepts all new flags
- Flag parsing works correctly
- Basic validation prevents invalid combinations
- Help text is clear and comprehensive

---

#### Task 3.2: Implement Interactive Prompts Logic
**Agent:** cli-developer
**Input:** Task 3.1 completed
**Output:** WpSetupCommand with interactive prompts
**Time:** 30min

**Steps:**
1. Add interactive prompts for installation type when not specified and not in non-interactive mode
2. Add interactive prompts for multisite mode when not specified and not in non-interactive mode
3. Use Laravel Prompts for interactive input (select, confirm, text)
4. Show clear descriptions for each option in prompts
5. Store selected values in variables for use in installation
6. Skip prompts when using `--no-interactive` flag
7. Add proper input validation for interactive prompts

**Completion Criteria:**
- [ ] Prompt for installation type when not specified and not in non-interactive mode
- [ ] Prompt for multisite mode when multisite flag not provided and not in non-interactive mode
- [ ] Clear descriptions shown for each option in prompts
- [ ] Prompts skipped when using `--no-interactive` flag
- [ ] Proper handling of user input validation
- [ ] Selected values stored in variables for installation logic
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Commands/Stack/WpSetupCommand.php`

**Validation:**
- Interactive prompts display correctly
- User input is captured and validated
- Prompts are skipped appropriately with `--no-interactive`
- Selected values are available for installation logic

---

#### Task 3.3: Integrate WordPressSetupService and Refactor Logic
**Agent:** cli-developer
**Input:** Task 3.2 completed
**Output:** WpSetupCommand fully integrated with WordPressSetupService
**Time:** 60min

**Steps:**
1. Replace existing installation logic with calls to WordPressSetupService
2. Use service to detect installation type and multisite mode
3. Delegate installation execution to WordPressSetupService methods
4. Maintain existing success/failure display logic
5. Preserve credential validation functionality
6. Ensure `--force` flag works with new installation types
7. Update configuration file writing logic as needed
8. Test all installation types and combinations

**Completion Criteria:**
- [ ] Command delegates to WordPressSetupService for installation logic
- [ ] Installation type detection works for all types
- [ ] Multisite mode detection works correctly
- [ ] `--force` flag works with new installation types
- [ ] Success/failure display logic preserved
- [ ] Credential validation functionality maintained
- [ ] All flag combinations work correctly
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Commands/Stack/WpSetupCommand.php`

**Validation:**
- All installation types work correctly
- Flag combinations work as expected
- Force reinstallation works for all types
- Service integration works properly
- No breaking changes to existing functionality

---

### Phase 4: Configuration & Stubs

#### Task 4.1: Create Multisite Configuration Stubs Structure
**Agent:** cli-developer
**Input:** Task 2.5 completed
**Output:** Multisite configuration stub directories and files
**Time:** 30min

**Steps:**
1. Create `stubs/stacks/wordpress/configs/multisite/` directory
2. Create `subdomain-wp-config.php` stub file
3. Create `subdirectory-wp-config.php` stub file
4. Include proper multisite constants in both stubs
5. Follow existing WordPress stub patterns and formatting
6. Add comprehensive comments explaining multisite configuration

**Completion Criteria:**
- [ ] `stubs/stacks/wordpress/configs/multisite/subdomain-wp-config.php` created
- [ ] `stubs/stacks/wordpress/configs/multisite/subdirectory-wp-config.php` created
- [ ] Stubs include `MULTISITE`, `SUBDOMAIN_INSTALL`, and `DOMAIN_CURRENT_SITE` constants
- [ ] Stubs include proper cookie domain configuration
- [ ] Stubs include VHOST detection code
- [ ] Stubs follow existing WordPress stub patterns
- [ ] No syntax errors in stub files

**Files:**
- Create: `stubs/stacks/wordpress/configs/multisite/subdomain-wp-config.php`
- Create: `stubs/stacks/wordpress/configs/multisite/subdirectory-wp-config.php`

**Validation:**
- Stub files have correct multisite configuration
- Subdomain and subdirectory configurations are distinct
- Constants are properly defined
- Comments are clear and helpful

---

#### Task 4.2: Extend WordPressEnvHandler for Multisite
**Agent:** php-pro
**Input:** Task 4.1 completed
**Output:** WordPressEnvHandler with multisite support
**Time:** 30min

**Steps:**
1. Add `configureMultisite()` method to WordPressEnvHandler
2. Add `WP_MULTISITE` environment variable
3. Add `WP_SUBDOMAIN_INSTALL` environment variable
4. Add `WP_DOMAIN_CURRENT_SITE` environment variable
5. Ensure method is called when multisite mode is selected
6. Use proper escaping of values for .env format
7. Follow existing .env handling patterns in the handler
8. Add comprehensive docblocks

**Completion Criteria:**
- [ ] Method `configureMultisite()` added to WordPressEnvHandler
- [ ] Adds `WP_MULTISITE` environment variable
- [ ] Adds `WP_SUBDOMAIN_INSTALL` environment variable
- [ ] Adds `WP_DOMAIN_CURRENT_SITE` environment variable
- [ ] Method called when multisite mode is selected
- [ ] Proper escaping of values for .env format
- [ ] Follows existing .env handling patterns
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Services/Support/EnvHandlers/WordPressEnvHandler.php`

**Validation:**
- Method correctly adds multisite environment variables
- Variables are properly formatted for .env files
- Integration with existing handler works correctly

---

#### Task 4.3: Extend BedrockEnvHandler for Multisite
**Agent:** php-pro
**Input:** Task 4.2 completed
**Output:** BedrockEnvHandler with multisite support
**Time:** 30min

**Steps:**
1. Add `configureMultisite()` method to BedrockEnvHandler
2. Add Bedrock-specific multisite environment variables
3. Ensure method is called when Bedrock multisite mode is selected
4. Ensure compatibility with Bedrock's configuration structure
5. Use proper escaping of values for .env format
6. Follow existing Bedrock .env handling patterns
7. Check Bedrock documentation for multisite support
8. Add comprehensive docblocks

**Completion Criteria:**
- [ ] Method `configureMultisite()` added to BedrockEnvHandler
- [ ] Adds Bedrock-specific multisite environment variables
- [ ] Method called when Bedrock multisite mode is selected
- [ ] Compatible with Bedrock's configuration structure
- [ ] Proper escaping of values for .env format
- [ ] Follows existing Bedrock .env handling patterns
- [ ] No syntax errors, passes PHPStan validation

**Files:**
- Modify: `app/Services/Support/EnvHandlers/BedrockEnvHandler.php`

**Validation:**
- Method correctly adds Bedrock multisite environment variables
- Variables are compatible with Bedrock structure
- Integration with existing handler works correctly

---

### Phase 5: Testing

#### Task 5.1: Write Unit Tests for WordPressType Enum
**Agent:** qa-expert
**Input:** Task 1.1 completed
**Output:** `tests/Unit/Enums/WordPressTypeTest.php`
**Time:** 30min

**Steps:**
1. Create `tests/Unit/Enums/WordPressTypeTest.php` test file
2. Test all enum cases (SINGLE, BEDROCK)
3. Test enum serialization/deserialization
4. Test enum value comparisons
5. Test enum can be used in type hints
6. Follow existing test patterns from `tests/Unit/`
7. Use Pest testing framework
8. Ensure comprehensive test coverage

**Completion Criteria:**
- [ ] `tests/Unit/Enums/WordPressTypeTest.php` created
- [ ] All enum cases tested (SINGLE, BEDROCK)
- [ ] Test enum serialization/deserialization
- [ ] Test enum value comparisons
- [ ] Test enum can be used in type hints
- [ ] All tests pass with `composer test:unit`
- [ ] Follows existing test patterns

**Files:**
- Create: `tests/Unit/Enums/WordPressTypeTest.php`

**Validation:**
- `composer test:unit` passes for new test file
- Test coverage includes all enum functionality
- Tests are well-structured and maintainable

---

#### Task 5.2: Write Unit Tests for MultisiteMode Enum
**Agent:** qa-expert
**Input:** Task 1.2 completed
**Output:** `tests/Unit/Enums/MultisiteModeTest.php`
**Time:** 30min

**Steps:**
1. Create `tests/Unit/Enums/MultisiteModeTest.php` test file
2. Test all enum cases (NONE, SUBDOMAIN, SUBDIRECTORY)
3. Test enum serialization/deserialization
4. Test enum value comparisons
5. Test enum can be used in type hints
6. Follow existing test patterns from `tests/Unit/`
7. Use Pest testing framework
8. Ensure comprehensive test coverage

**Completion Criteria:**
- [ ] `tests/Unit/Enums/MultisiteModeTest.php` created
- [ ] All enum cases tested (NONE, SUBDOMAIN, SUBDIRECTORY)
- [ ] Test enum serialization/deserialization
- [ ] Test enum value comparisons
- [ ] Test enum can be used in type hints
- [ ] All tests pass with `composer test:unit`
- [ ] Follows existing test patterns

**Files:**
- Create: `tests/Unit/Enums/MultisiteModeTest.php`

**Validation:**
- `composer test:unit` passes for new test file
- Test coverage includes all enum functionality
- Tests are well-structured and maintainable

---

#### Task 5.3: Write Unit Tests for WordPressSetupService
**Agent:** qa-expert
**Input:** Task 2.1-2.4 completed
**Output:** `tests/Unit/Services/WordPress/WordPressSetupServiceTest.php`
**Time:** 90min

**Steps:**
1. Create `tests/Unit/Services/WordPress/WordPressSetupServiceTest.php` test file
2. Test `detectInstallationType()` for all scenarios
3. Test `detectMultisiteMode()` for all scenarios
4. Test `configureStandardWordPress()` with valid/invalid inputs
5. Test `configureBedrock()` with valid/invalid inputs
6. Test `configureMultisite()` for both modes
7. Test `validateConfiguration()` error handling
8. Mock dependencies (DockerExecutorInterface, WordPressStackInstaller, JsonFileService)
9. Follow existing test patterns from service tests
10. Use Mockery for mocking dependencies

**Completion Criteria:**
- [ ] `tests/Unit/Services/WordPress/WordPressSetupServiceTest.php` created
- [ ] Test `detectInstallationType()` for all scenarios
- [ ] Test `detectMultisiteMode()` for all scenarios
- [ ] Test `configureStandardWordPress()` with valid/invalid inputs
- [ ] Test `configureBedrock()` with valid/invalid inputs
- [ ] Test `configureMultisite()` for both modes
- [ ] Test `validateConfiguration()` error handling
- [ ] Mock dependencies correctly
- [ ] All tests pass with `composer test:unit`
- [ ] Follows existing test patterns

**Files:**
- Create: `tests/Unit/Services/WordPress/WordPressSetupServiceTest.php`

**Validation:**
- `composer test:unit` passes for new test file
- Test coverage includes all service methods
- Tests handle success and failure scenarios
- Mocking is correct and comprehensive

---

#### Task 5.4: Extend WpSetupCommand Tests with New Functionality
**Agent:** qa-expert
**Input:** Task 3.1-3.3 completed
**Output:** Extended `tests/Feature/Console/WpSetupCommandTest.php`
**Time:** 90min

**Steps:**
1. Extend existing `tests/Feature/Console/WpSetupCommandTest.php` with new tests
2. Test `--type` flag for standard and bedrock
3. Test `--multisite` flag for all modes
4. Test `--no-interactive` flag behavior
5. Test `--type` and `--multisite` flag combinations
6. Test interactive prompts are shown when expected
7. Test interactive prompts are skipped with `--no-interactive`
8. Test invalid flag combinations show errors
9. Test force reinstallation works with new types
10. Test with existing standard WordPress project
11. Test with existing Bedrock project
12. Use `Process::fake()` for Docker commands
13. Mock DockerExecutorInterface
14. Follow existing test patterns in WpSetupCommandTest

**Completion Criteria:**
- [ ] Test `--type` flag for standard and bedrock
- [ ] Test `--multisite` flag for all modes
- [ ] Test `--no-interactive` flag behavior
- [ ] Test `--type` and `--multisite` flag combinations
- [ ] Test interactive prompts are shown when expected
- [ ] Test interactive prompts are skipped with `--no-interactive`
- [ ] Test invalid flag combinations show errors
- [ ] Test force reinstallation still works
- [ ] Test with existing standard WordPress project
- [ ] Test with existing Bedrock project
- [ ] All tests pass with `composer test:unit`
- [ ] Follows existing test patterns

**Files:**
- Modify: `tests/Feature/Console/WpSetupCommandTest.php`

**Validation:**
- `composer test:unit` passes for extended test file
- Test coverage includes all new command functionality
- Tests handle success and failure scenarios
- Interactive prompt testing is comprehensive

---

#### Task 5.5: Write Integration Tests for Multisite Setup
**Agent:** qa-expert
**Input:** Task 4.1-4.3 completed
**Output:** `tests/Feature/WordPress/MultisiteSetupTest.php`
**Time:** 60min

**Steps:**
1. Create `tests/Feature/WordPress/MultisiteSetupTest.php` test file
2. Test complete subdomain multisite installation
3. Test complete subdirectory multisite installation
4. Test adding sites to existing multisite network
5. Test multisite detection on existing installations
6. Test configuration files are correct after setup
7. Test with both standard and Bedrock installations
8. Use test helpers from `tests/Feature/Concerns/`
9. Create test WordPress projects with multisite config
10. Verify `.env` and `wp-config.php` are correct
11. Verify WP-CLI commands are called correctly
12. Use `createTestDirectory()` and `cleanupTestDirectory()` helpers

**Completion Criteria:**
- [ ] Test complete subdomain multisite installation
- [ ] Test complete subdirectory multisite installation
- [ ] Test adding sites to existing multisite network
- [ ] Test multisite detection on existing installations
- [ ] Test configuration files are correct after setup
- [ ] Test with both standard and Bedrock installations
- [ ] All tests pass with `composer test:unit`
- [ ] Uses appropriate test helpers and cleanup

**Files:**
- Create: `tests/Feature/WordPress/MultisiteSetupTest.php`

**Validation:**
- `composer test:unit` passes for new test file
- Test coverage includes end-to-end multisite scenarios
- Tests verify actual configuration files are correct
- Test setup and cleanup is proper

---

### Phase 6: Documentation

#### Task 6.1: Update Command Help Text
**Agent:** documentation-engineer
**Input:** Task 3.1-3.3 completed
**Output:** Updated WpSetupCommand help text
**Time:** 30min

**Steps:**
1. Update WpSetupCommand description to mention all installation types
2. Add help text for `--type` flag with examples
3. Add help text for `--multisite` flag with examples
4. Add help text for `--no-interactive` flag
5. Add examples section with common usage patterns
6. Ensure all text is clear and concise
7. Update inline comments for flag descriptions
8. Include examples in docblock

**Completion Criteria:**
- [ ] Command description mentions all installation types
- [ ] Help text for `--type` flag with examples
- [ ] Help text for `--multisite` flag with examples
- [ ] Help text for `--no-interactive` flag
- [ ] Examples section added with common usage patterns
- [ ] All text is clear and concise
- [ ] No syntax errors in docblocks

**Files:**
- Modify: `app/Commands/Stack/WpSetupCommand.php`

**Validation:**
- Help text is comprehensive and clear
- Examples are accurate and helpful
- All flags are properly documented

---

#### Task 6.2: Create WordPress Setup Documentation
**Agent:** documentation-engineer
**Input:** Task 6.1 completed
**Output:** Comprehensive WordPress setup documentation
**Time:** 60min

**Steps:**
1. Create appropriate documentation location (docs/ or README section)
2. Write section on installation types (Standard vs Bedrock)
3. Write section on Multisite setup (subdomain vs subdirectory)
4. Write section on Interactive vs Non-interactive mode
5. Include examples for all usage scenarios
6. Add troubleshooting section for common issues
7. Add code examples for all flag combinations
8. Use clear, concise language
9. Include code blocks with command examples
10. Add links to WordPress and Bedrock documentation
11. Document environment variables used
12. Document configuration files created

**Completion Criteria:**
- [ ] Documentation created at appropriate location
- [ ] Section on installation types (Standard vs Bedrock)
- [ ] Section on Multisite setup (subdomain vs subdirectory)
- [ ] Section on Interactive vs Non-interactive mode
- [ ] Examples for all usage scenarios
- [ ] Troubleshooting section for common issues
- [ ] Code examples for all flag combinations
- [ ] Clear, concise language with proper formatting
- [ ] Links to external documentation

**Files:**
- Create: `docs/wordpress-setup.md` (or appropriate location)

**Validation:**
- Documentation is comprehensive and accurate
- Examples work correctly
- Language is clear and helpful
- All features are properly documented

---

#### Task 6.3: Update CLAUDE.md with New Patterns
**Agent:** documentation-engineer
**Input:** Task 6.2 completed
**Output:** Updated CLAUDE.md with WordPress setup patterns
**Time:** 30min

**Steps:**
1. Update CLAUDE.md to document new WordPress setup patterns and enums
2. Add WordPressType enum to relevant sections
3. Add MultisiteMode enum to relevant sections
4. Add WordPressSetupService to Common Tasks table
5. Add new flags to Command Interface section
6. Add new stub directories to Directory Structure
7. Ensure all changes follow CLAUDE.md formatting standards
8. Update "Directory Structure" section with new paths
9. Update "Common Tasks" table with WordPress setup tasks
10. Ensure all changes are consistent with existing content

**Completion Criteria:**
- [ ] WordPressType enum added to relevant sections
- [ ] MultisiteMode enum added to relevant sections
- [ ] WordPressSetupService added to Common Tasks table
- [ ] New flags documented in Command Interface section
- [ ] New stub directories documented in Directory Structure
- [ ] All changes follow CLAUDE.md formatting standards
- [ ] All changes are consistent with existing content

**Files:**
- Modify: `CLAUDE.md`

**Validation:**
- CLAUDE.md is updated consistently
- New patterns are properly documented
- References are correct and helpful

---

## Commit Checkpoints

| CP | Tasks | Commit Message |
|----|-------|----------------|
| 1 | 1.1, 1.2 | feat(wordpress): add installation type and multisite mode enums |
| 2 | 2.1-2.5, 5.1-5.2 | feat(wordpress): implement WordPressSetupService and multisite support in installer |
| 3 | 3.1-3.3, 5.4 | feat(wordpress): update WpSetupCommand with new flags and interactive prompts |
| 4 | 4.1-4.3, 5.5 | feat(wordpress): add multisite configuration stubs and env handlers |
| 5 | 6.1-6.3 | docs(wordpress): update documentation for installation enhancements |

## Dependencies Graph

```
Task 1.1 (WordPressType) ──┬──→ Task 2.1 (WordPressSetupService structure)
                         │
Task 1.2 (MultisiteMode) ──┼──→ Task 2.1 (WordPressSetupService structure)
                         │
                         ├──→ Task 2.2 (Installation detection)
                         │
                         ├──→ Task 2.3 (Configuration validation)
                         │
                         ├──→ Task 2.4 (Installation methods)
                         │
                         └──→ Task 2.5 (Multisite in installer)
                                 │
                                 ├──→ Task 3.1-3.3 (WpSetupCommand)
                                 │        │
                                 │        └──→ Task 5.4 (Command tests)
                                 │
                                 ├──→ Task 4.1-4.3 (Config & stubs)
                                 │        │
                                 │        └──→ Task 5.5 (Integration tests)
                                 │
                                 └──→ Task 5.1-5.3 (Unit tests)
                                          │
                                          └──→ Task 6.1-6.3 (Documentation)
```

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Multisite configuration complexity | Medium | High | Thorough testing with both subdomain and subdirectory modes; reference WordPress documentation closely |
| Bedrock multisite compatibility | Medium | Medium | Verify Bedrock supports multisite; test with actual Bedrock installation; consider deferring to Phase 2 if needed |
| Interactive prompt UX | Low | Medium | Follow Laravel Prompts best practices; test with real users; provide clear descriptions |
| Breaking existing functionality | Low | High | Comprehensive regression tests; run existing test suite before merging; test with existing WordPress projects |
| Configuration file conflicts | Low | Medium | Validate existing configuration before writing; provide clear error messages; support configuration migration |
| WP-CLI command compatibility | Low | Medium | Test with multiple WP-CLI versions; check WP-CLI documentation; handle version-specific differences |

## Notes

### Implementation Phases

The feature can be implemented in phases to reduce risk:

**Phase 1 (MVP - Tasks 1.1-1.2, 2.1-2.4, 3.1-3.3, 5.1-5.4):**
- Standard and Bedrock installation types
- Interactive prompts
- Basic testing
- Deferred: Multisite support

**Phase 2 (Multisite - Tasks 2.5, 4.1-4.3, 5.5):**
- Multisite support (subdomain and subdirectory)
- Multisite configuration stubs
- Integration tests

**Phase 3 (Polish - Tasks 6.1-6.3):**
- Documentation updates
- Help text improvements
- Edge case handling

### User Decisions Summary

| Decision | Choice |
|----------|--------|
| Default behavior | Interactive by default |
| Non-interactive mode | `--no-interactive` flag |
| Reinstall approach | `--force` flag (no separate command) |
| Type configuration | Two flags: `--type` + `--multisite` |
| Bedrock scope | Full setup with Composer |
| Config storage | `.tuti/config.json` (settings) + `.env` (credentials) |

### Technical Considerations

1. **WP-CLI Version Compatibility**: Ensure WP-CLI commands used are compatible with the WP-CLI version in the Docker image
2. **Bedrock Multisite**: Verify Bedrock supports multisite out of the box or requires additional configuration
3. **Environment Variables**: Use clear, descriptive variable names to avoid conflicts
4. **Configuration Migration**: Consider how to handle existing projects when adding multisite
5. **Testing**: Use Docker Compose to spin up test containers for integration tests
6. **Security**: Validate all user inputs; use array syntax for all Process executions
7. **Error Messages**: Provide clear, actionable error messages for common issues

### Related Files

- Existing WordPress installation: `app/Commands/Stack/WpSetupCommand.php`
- WordPress installer: `app/Services/Stack/Installers/WordPressStackInstaller.php`
- Env handlers: `app/Services/Support/EnvHandlers/WordPressEnvHandler.php`, `BedrockEnvHandler.php`
- Stack definitions: `stubs/stacks/wordpress/stack.json`
- Test patterns: `tests/Feature/Console/WpSetupCommandTest.php`
- Security patterns: `app/Services/Docker/DockerExecutorService.php` (array syntax examples)