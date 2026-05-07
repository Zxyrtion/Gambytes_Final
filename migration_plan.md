# MIGRATION PLAN: Remove Old Signature Tables

## Goal
Remove all references to `signed_documents` and `family_signed_documents` tables and use the new unified `signed_contract_documents` table.

## Files That Need Updates

### 1. **app/views/Users/Family member/my-contracts.php**
- Lines 113-130: Remove `family_signed_documents` table creation and queries
- Remove all `$rehabAgreement` related code
- Focus only on `contract_submissions` table

### 2. **app/views/Users/Gamblers/contract/fill-contract.php**
- Lines 189-202: Remove `signed_documents` queries
- Remove `$signed` variable usage
- Use only `contract_submissions` table

### 3. **api/save_rehab_agreement.php**
- ENTIRE FILE: Replace with new logic using `signed_contract_documents`
- Or deprecate if not needed

### 4. **api/save_family_contract.php**
- ENTIRE FILE: Replace with new logic using `signed_contract_documents`
- Or deprecate if not needed

### 5. **api/verify_signature.php**
- Line 30-33: Update to query `signed_contract_documents` instead of `signed_documents`

### 6. **app/views/Users/Executive assistant/view-family-contract.php**
- Lines 34-59: Update to query `signed_contract_documents` instead of `family_signed_documents`

### 7. **app/views/Users/Executive assistant/view-contract.php**
- Lines 60-77: Update to query `signed_contract_documents` instead of `signed_documents`

### 8. **app/views/Users/Executive assistant/contract-verification.php**
- Lines 95-100: Update queries to use `signed_contract_documents`

### 9. **debug_submissions_array.php**
- Line 111: Remove `family_signed_documents` query (debug file, can be deleted)

## New Unified Approach

### Current System (OLD - Multiple Tables)
```
contract_submissions (gambler_sig, family_sig) ❌
signed_documents (gambler only) ❌
family_signed_documents (family only) ❌
```

### New System (CLEAN - Single Source of Truth)
```
contract_submissions (NO signatures, just workflow data) ✅
signed_contract_documents (ALL signatures) ✅
```

## Migration Steps

1. ✅ Create `signed_contract_documents` table (DONE)
2. ✅ Remove signature columns from `contract_documents` (DONE)
3. 🔄 Update all PHP files to use new structure (IN PROGRESS)
4. 🔄 Test the complete flow
5. ⏳ Drop old tables (after testing)

## Testing Checklist

- [ ] Gambler can sign contract
- [ ] Family can sign contract
- [ ] Signatures are stored in `signed_contract_documents`
- [ ] Executive assistant can view signed contracts
- [ ] Supervisor can verify contracts
- [ ] No errors in PHP logs
