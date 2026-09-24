# handler_missing_context: stop catching the exception links::page() throws for a row whose context is
# gone, so /p/<code> answers an error page instead of core's "not found".
s|catch \(\\dml_missing_record_exception \$exception\)|catch (\\coding_exception \$exception)|;
