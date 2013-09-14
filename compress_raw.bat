@ECHO OFF
 DIR /S /B /A-D "%CD%" > "%TEMP%\tempfiles.txt"
 FOR /F "usebackq tokens=* delims=" %%a IN ("%TEMP%\tempfiles.txt") DO CALL :APPEND "%%a"
 
ECHO Done!
PAUSE > NUL
EXIT
 
 
:APPEND
SET FILE=%1
IF %FILE% EQU "%CD%\append.bat" GOTO :EOF
TYPE %FILE% >> "%CD%\every_raw.txt"
GOTO :EOF