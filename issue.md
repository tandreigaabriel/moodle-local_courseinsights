Use this prompt for your developer:

Fix the Moodle PHPDoc CI failure in classes/report_service.php near line 2370. The method report_service::get_module_completion_funnel() has an incomplete PHPDoc parameter list. Compare the method signature with the docblock immediately above it and ensure every function parameter has a corresponding @param entry, using the exact parameter name and the same order. Remove any obsolete @param entries and ensure the method also has an accurate @return annotation. Then rerun:

moodle-plugin-ci phpdoc ./plugin --max-warnings 0
